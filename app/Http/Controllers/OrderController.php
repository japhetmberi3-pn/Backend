<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ProductPurchasedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Afficher les commandes selon le rôle de l'utilisateur connecté.
     *
     * Client  : ses propres commandes
     * Admin   : toutes les commandes
     * Vendeur : commandes contenant ses produits
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::with([
            'user',
            'items.product.user',
            'items.product.shop',
        ])->latest();

        // CLIENT
        if ($user->role === 'client') {
            $query->where('user_id', $user->id);
        }

        // VENDEUR
        elseif (
            $user->role === 'vendeur' ||
            $user->role === 'seller'
        ) {
            $query->whereHas('items.product', function ($productQuery) use ($user) {
                $productQuery->where('user_id', $user->id);
            });
        }

        // ADMIN
        elseif ($user->role === 'admin') {
            // L'admin voit toutes les commandes.
        }

        // Rôle inconnu
        else {
            return response()->json([
                'message' => 'Rôle utilisateur non autorisé.',
            ], 403);
        }

        return response()->json(
            $query->get()
        );
    }

    /**
     * Afficher une commande spécifique.
     */
    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        $authorized = false;

        // Le client peut voir uniquement ses commandes.
        if (
            $user->role === 'client' &&
            $order->user_id === $user->id
        ) {
            $authorized = true;
        }

        // L'admin peut voir toutes les commandes.
        elseif ($user->role === 'admin') {
            $authorized = true;
        }

        // Le vendeur peut voir une commande
        // si elle contient au moins un de ses produits.
        elseif (
            $user->role === 'vendeur' ||
            $user->role === 'seller'
        ) {
            $authorized = $order->items()
                ->whereHas('product', function ($productQuery) use ($user) {
                    $productQuery->where('user_id', $user->id);
                })
                ->exists();
        }

        if (!$authorized) {
            return response()->json([
                'message' => 'Vous n’êtes pas autorisé à voir cette commande.',
            ], 403);
        }

        return response()->json(
            $order->load([
                'user',
                'items.product.user',
                'items.product.shop',
            ])
        );
    }

    /**
     * Créer une commande.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Produits
            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            // Destinataire
            'recipient_name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            // Adresse
            'city' => [
                'required',
                'string',
                'max:255',
            ],

            'neighborhood' => [
                'required',
                'string',
                'max:255',
            ],

            'address' => [
                'required',
                'string',
                'max:1000',
            ],

            'address_details' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // Position GPS
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            // Livraison
            'delivery_note' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'delivery_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'delivery_time' => [
                'required',
                'string',
                'max:50',
            ],
        ]);

        $items = $validated['items'];
        $client = $request->user();

        try {
            $order = DB::transaction(function () use (
                $items,
                $validated,
                $client
            ) {
                // Créer la commande
                $order = Order::create([
                    'user_id' => $client->id,
                    'status' => 'pending',

                    'recipient_name' => $validated['recipient_name'],
                    'phone' => $validated['phone'],

                    'city' => $validated['city'],
                    'neighborhood' => $validated['neighborhood'],
                    'address' => $validated['address'],
                    'address_details' => $validated['address_details'] ?? null,

                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],

                    'delivery_note' => $validated['delivery_note'] ?? null,

                    'delivery_date' => $validated['delivery_date'],
                    'delivery_time' => $validated['delivery_time'],
                ]);

                // Récupérer le panier du client
                $cart = Cart::where(
                    'user_id',
                    $client->id
                )->first();

                // Traiter chaque produit
                foreach ($items as $itemData) {
                    $product = Product::lockForUpdate()
                        ->find($itemData['product_id']);

                    if (!$product) {
                        abort(
                            422,
                            'Le produit demandé n’existe plus.'
                        );
                    }

                    $quantity = $itemData['quantity'];

                    // Vérifier le stock
                    if ($product->stock < $quantity) {
                        abort(
                            422,
                            'Stock insuffisant pour le produit "' .
                            $product->name .
                            '". Stock disponible : ' .
                            $product->stock .
                            '.'
                        );
                    }

                    // Ajouter le produit à la commande
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $product->price,
                    ]);

                    // Diminuer le stock
                    $product->decrement(
                        'stock',
                        $quantity
                    );

                    // Mettre à jour le panier
                    if ($cart) {
                        $cartItem = $cart->items()
                            ->where(
                                'product_id',
                                $product->id
                            )
                            ->first();

                        if ($cartItem) {
                            if ($quantity >= $cartItem->quantity) {
                                $cartItem->delete();
                            } else {
                                $cartItem->decrement(
                                    'quantity',
                                    $quantity
                                );
                            }
                        }
                    }

                    // Notification du client
                    $client->notify(
                        new ProductPurchasedNotification(
                            $product->id,
                            $product->name,
                            $quantity,
                            $client->name
                        )
                    );

                    // Notification de l'administrateur
                    $admin = User::where(
                        'role',
                        'admin'
                    )->first();

                    if ($admin) {
                        $admin->notify(
                            new ProductPurchasedNotification(
                                $product->id,
                                $product->name,
                                $quantity,
                                $client->name
                            )
                        );
                    }
                }

                return $order;
            });

            return response()->json([
                'message' => 'Commande créée avec succès.',
                'order' => $order->load([
                    'user',
                    'items.product.user',
                    'items.product.shop',
                ]),
            ], 201);

        } catch (\Throwable $e) {
            if ($e->getCode() === 422) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création de la commande.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Modifier le statut d'une commande.
     *
     * Admin   : peut modifier toutes les commandes.
     * Vendeur : peut modifier les commandes contenant ses produits.
     * Client  : interdit.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $user = $request->user();

        // Le client ne peut pas modifier le statut.
        if ($user->role === 'client') {
            return response()->json([
                'message' => 'Vous n’êtes pas autorisé à modifier le statut d’une commande.',
            ], 403);
        }

        // Vérifier le rôle.
        if (
            $user->role !== 'admin' &&
            $user->role !== 'vendeur' &&
            $user->role !== 'seller'
        ) {
            return response()->json([
                'message' => 'Rôle utilisateur non autorisé.',
            ], 403);
        }

        // Le vendeur ne peut modifier que les commandes
        // contenant ses propres produits.
        if (
            $user->role === 'vendeur' ||
            $user->role === 'seller'
        ) {
            $hasOwnProduct = $order->items()
                ->whereHas('product', function ($productQuery) use ($user) {
                    $productQuery->where('user_id', $user->id);
                })
                ->exists();

            if (!$hasOwnProduct) {
                return response()->json([
                    'message' => 'Vous n’êtes pas autorisé à modifier cette commande.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:pending,confirmed,preparing,shipped,delivered,cancelled',
            ],
        ]);

        $order->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Statut de la commande mis à jour avec succès.',
            'order' => $order->load([
                'user',
                'items.product.user',
                'items.product.shop',
            ]),
        ]);
    }
}