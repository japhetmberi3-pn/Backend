<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Afficher le panier de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $cart->load('items.product');

        return response()->json([
            'cart' => $cart,
            'items' => $cart->items,
        ]);
    }

    /**
     * Ajouter un produit au panier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $quantity = $validated['quantity'] ?? 1;

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $item->increment('quantity', $quantity);
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        $item->load('product');

        return response()->json([
            'message' => 'Produit ajouté au panier avec succès.',
            'item' => $item,
        ], 201);
    }

    /**
     * Modifier la quantité d'un article du panier.
     */
    public function update(Request $request, CartItem $item)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::where('user_id', $request->user()->id)
            ->first();

        if (!$cart || $item->cart_id !== $cart->id) {
            return response()->json([
                'message' => 'Cet article n’appartient pas à votre panier.'
            ], 403);
        }

        $item->update([
            'quantity' => $validated['quantity'],
        ]);

        $item->load('product');

        return response()->json([
            'message' => 'Quantité mise à jour avec succès.',
            'item' => $item,
        ]);
    }

    /**
     * Supprimer un article du panier.
     */
    public function destroy(Request $request, CartItem $item)
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->first();

        if (!$cart || $item->cart_id !== $cart->id) {
            return response()->json([
                'message' => 'Cet article n’appartient pas à votre panier.'
            ], 403);
        }

        $item->delete();

        return response()->json([
            'message' => 'Article supprimé du panier avec succès.'
        ]);
    }
}