<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Afficher les produits.
     *
     * Admin  : tous les produits.
     * Vendeur : uniquement les produits de sa boutique.
     * Client  : tous les produits.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Vendeur : uniquement les produits de sa boutique
        if (in_array($user->role, ['vendeur', 'seller'])) {
            $shop = $user->shop;

            if (!$shop) {
                return response()->json([]);
            }

            $products = Product::with(['user', 'shop'])
                ->where('shop_id', $shop->id)
                ->latest()
                ->get();

            return response()->json($products);
        }

        // Admin et client : tous les produits
        $products = Product::with(['user', 'shop'])
            ->latest()
            ->get();

        return response()->json($products);
    }

    /**
     * Créer un produit.
     *
     * Le vendeur ajoute automatiquement son produit
     * dans sa propre boutique.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        /*
         * Le vendeur doit avoir une boutique.
         */
        if (in_array($user->role, ['vendeur', 'seller'])) {
            $shop = $user->shop;

            if (!$shop) {
                return response()->json([
                    'message' => 'Vous devez créer une boutique avant de créer un produit.',
                ], 422);
            }

            $product = Product::create([
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock' => $validated['stock'],
            ]);

            return response()->json([
                'message' => 'Produit créé avec succès.',
                'product' => $product->load(['user', 'shop']),
            ], 201);
        }

        /*
         * Admin :
         * on permet la création d'un produit.
         *
         * S'il possède une boutique, on l'associe automatiquement.
         * Sinon shop_id reste null.
         */
        $shopId = $user->shop?->id;

        $product = Product::create([
            'user_id' => $user->id,
            'shop_id' => $shopId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
        ]);

        return response()->json([
            'message' => 'Produit créé avec succès.',
            'product' => $product->load(['user', 'shop']),
        ], 201);
    }

    /**
     * Afficher un produit.
     */
    public function show(Product $product)
    {
        return response()->json(
            $product->load(['user', 'shop'])
        );
    }

    /**
     * Modifier un produit.
     *
     * Admin  : tous les produits.
     * Vendeur : uniquement les produits de sa boutique.
     */
    public function update(
        Request $request,
        Product $product
    ) {
        $user = $request->user();

        /*
         * VENDEUR
         */
        if (in_array($user->role, ['vendeur', 'seller'])) {
            $shop = $user->shop;

            if (!$shop) {
                return response()->json([
                    'message' => 'Vous devez avoir une boutique pour modifier un produit.',
                ], 403);
            }

            if ($product->shop_id !== $shop->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez modifier que les produits de votre boutique.',
                ], 403);
            }
        }

        /*
         * Les champs modifiables sont les mêmes
         * pour admin et vendeur.
         */
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Produit modifié avec succès.',
            'product' => $product->load(['user', 'shop']),
        ]);
    }

    /**
     * Supprimer un produit.
     *
     * Admin  : tous les produits.
     * Vendeur : uniquement les produits de sa boutique.
     */
    public function destroy(
        Request $request,
        Product $product
    ) {
        $user = $request->user();

        /*
         * VENDEUR
         */
        if (in_array($user->role, ['vendeur', 'seller'])) {
            $shop = $user->shop;

            if (!$shop) {
                return response()->json([
                    'message' => 'Vous devez avoir une boutique pour supprimer un produit.',
                ], 403);
            }

            if ($product->shop_id !== $shop->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez supprimer que les produits de votre boutique.',
                ], 403);
            }
        }

        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès.',
        ]);
    }
}