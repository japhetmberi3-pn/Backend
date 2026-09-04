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
     * Acheter plusieurs articles sélectionnés du panier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $client = $request->user();

        $order = DB::transaction(function () use ($validated, $client) {

            // Récupérer le panier du client
            $cart = Cart::where('user_id', $client->id)
                ->first();

            if (!$cart) {
                abort(422, 'Votre panier est vide.');
            }

            // Créer la commande
            $order = Order::create([
                'user_id' => $client->id,
                'status' => 'completed',
            ]);

            foreach ($validated['items'] as $itemData) {

                // Vérifier que le produit appartient bien au panier
                $cartItem = $cart->items()
                    ->where('product_id', $itemData['product_id'])
                    ->first();

                if (!$cartItem) {
                    abort(
                        422,
                        'Le produit ID ' . $itemData['product_id'] . ' n’est pas dans votre panier.'
                    );
                }

                // Vérifier le stock
                $product = Product::lockForUpdate()
                    ->findOrFail($itemData['product_id']);

                if ($product->stock < $itemData['quantity']) {
                    abort(
                        422,
                        'Stock insuffisant pour le produit "' . $product->name . '".'
                    );
                }

                // Vérifier que la quantité demandée
                // ne dépasse pas celle du panier
                if ($itemData['quantity'] > $cartItem->quantity) {
                    abort(
                        422,
                        'La quantité demandée pour "' . $product->name .
                        '" dépasse la quantité présente dans le panier.'
                    );
                }

                // Ajouter le produit à la commande
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $itemData['quantity'],
                    'price' => $product->price,
                ]);

                // Retirer du stock
                $product->decrement('stock', $itemData['quantity']);

                // Si toute la quantité du panier est achetée,
                // supprimer complètement l'article du panier.
                if ($itemData['quantity'] == $cartItem->quantity) {
                    $cartItem->delete();
                } else {
                    // Sinon, diminuer seulement la quantité.
                    $cartItem->decrement(
                        'quantity',
                        $itemData['quantity']
                    );
                }

                // Notification du client
                $client->notify(
                    new ProductPurchasedNotification(
                        $product->id,
                        $product->name,
                        $itemData['quantity'],
                        $client->name
                    )
                );

                // Notification de l'administrateur
                $admin = User::where('role', 'admin')->first();

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

        return response()->json([
            'message' => 'Achat effectué avec succès.',
            'order' => $order->load('items.product'),
        ], 201);
    }
}