<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Afficher tous les produits.
     */
    public function index()
    {
        $products = Product::with('user')->latest()->get();

        return response()->json($products);
    }

    /**
     * Créer un produit.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $product = Product::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
        ]);

        return response()->json([
            'message' => 'Produit créé avec succès.',
            'product' => $product,
        ], 201);
    }

    /**
     * Afficher un produit.
     */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /**
     * Modifier un produit.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Produit modifié avec succès.',
            'product' => $product,
        ]);
    }

    /**
     * Supprimer un produit.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès.',
        ]);
    }
}
