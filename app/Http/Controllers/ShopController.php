<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * Afficher toutes les boutiques.
     */
    public function all()
    {
        $shops = Shop::with('products')
            ->latest()
            ->get();

        return response()->json($shops);
    }

    /**
     * Afficher la boutique du vendeur connecté.
     */
    public function index(Request $request)
    {
        $shop = $request->user()
            ->shop()
            ->with('products')
            ->first();

        if (!$shop) {
            return response()->json([
                'message' => 'Vous n\'avez pas encore de boutique.',
            ], 404);
        }

        return response()->json($shop);
    }

    /**
     * Créer la boutique du vendeur connecté.
     */
    public function store(Request $request)
    {
        if ($request->user()->shop) {
            return response()->json([
                'message' => 'Vous avez déjà une boutique.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $shop = Shop::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Boutique créée avec succès.',
            'shop' => $shop,
        ], 201);
    }
}