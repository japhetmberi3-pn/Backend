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
     * Afficher les commandes du client connecté.
     */
    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with('items.product')
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * Afficher une commande spécifique.
     */
    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n’êtes pas autorisé à voir cette commande.'
            ], 403);
        }

        return response()->json(
            $order->load('items.product')
        );
    }

    /**
     * Créer une commande directement depuis la page Commandes.
     */
    public function store(Request $request)
    {
        // Récupérer les données envoyées par Next.js
        $items = $request->input('items');

        // Vérifier que items existe
        if (!is_array($items) || count($items) === 0) {
            return response()->json([
                'message' => 'Le champ items est obligatoire et doit contenir au moins un article.',
                'received' => $request->all(),
            ], 422);
        }

        // Vérifier chaque article
        foreach ($items as $index => $item) {

            if (!isset($item['product_id'])) {
                return response()->json([
                    'message' => "Le product_id est manquant pour l'article " . ($index + 1) . ".",
                ], 422);
            }

            if (!isset($item['quantity'])) {
                return response()->json([
                    'message' => "La quantity est manquante pour l'article " . ($index + 1) . ".",
                ], 422);
            }

            if (!is_numeric($item['product_id'])) {
                return response()->json([
                    'message' => "Le product_id doit être un nombre.",
                ], 422);
            }

            if (!is_numeric($item['quantity']) || $item['quantity'] < 1) {
                return response()->json([
                    'message' => "La quantité doit être supérieure ou égale à 1.",
                ], 422);
            }

            if (!Product::where('id', $item['product_id'])->exists()) {
                return response()->json([
                    'message' => "Le produit ID {$item['product_id']} n'existe pas.",
                ], 422);
            }
        }

        $client = $request->user();

        try {

            $order = DB::transaction(function () use ($items, $client) {

                /*
                 * Créer la commande
                 */
                $order = Order::create([
                    'user_id' => $client->id,
                    'status' => 'pending',
                ]);

                /*
                 * Récupérer le panier s'il existe.
                 *
                 * Le panier n'est plus obligatoire.
                 */
                $cart = Cart::where('user_id', $client->id)
                    ->first();

                foreach ($items as $itemData) {

                    /*
                     * Verrouiller le produit pendant
                     * toute la transaction.
                     */
                    $product = Product::lockForUpdate()
                        ->find($itemData['product_id']);

                    if (!$product) {
                        abort(
                            422,
                            'Le produit demandé n’existe plus.'
                        );
                    }

                    /*
                     * Vérifier le stock
                     */
                    if ($product->stock < $itemData['quantity']) {
                        abort(
                            422,
                            'Stock insuffisant pour le produit "' .
                            $product->name .
                            '". Stock disponible : ' .
                            $product->stock .
                            '.'
                        );
                    }

                    /*
                     * Ajouter l'article à la commande
                     */
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $itemData['quantity'],
                        'price' => $product->price,
                    ]);

                    /*
                     * Diminuer le stock
                     */
                    $product->decrement(
                        'stock',
                        $itemData['quantity']
                    );

                    /*
                     * Si le produit existe également
                     * dans le panier, on ajuste le panier.
                     *
                     * Mais le panier n'est PAS obligatoire.
                     */
                    if ($cart) {

                        $cartItem = $cart->items()
                            ->where(
                                'product_id',
                                $product->id
                            )
                            ->first();

                        if ($cartItem) {

                            if (
                                $itemData['quantity'] >=
                                $cartItem->quantity
                            ) {
                                $cartItem->delete();
                            } else {
                                $cartItem->decrement(
                                    'quantity',
                                    $itemData['quantity']
                                );
                            }
                        }
                    }

                    /*
                     * Notification du client
                     */
                    $client->notify(
                        new ProductPurchasedNotification(
                            $product->id,
                            $product->name,
                            $itemData['quantity'],
                            $client->name
                        )
                    );

                    /*
                     * Notification de l'administrateur
                     */
                    $admin = User::where(
                        'role',
                        'admin'
                    )->first();

                    if ($admin) {

                        $admin->notify(
                            new ProductPurchasedNotification(
                                $product->id,
                                $product->name,
                                $itemData['quantity'],
                                $client->name
                            )
                        );
                    }
                }

                return $order;
            });

            /*
             * Réponse envoyée à Next.js
             */
            return response()->json([
                'message' => 'Achat effectué avec succès.',
                'order' => $order->load('items.product'),
            ], 201);

        } catch (\Throwable $e) {

            /*
             * Erreur métier
             */
            if ($e->getCode() === 422) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            /*
             * Erreur serveur
             */
            return response()->json([
                'message' => 'Une erreur est survenue lors de la création de la commande.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}