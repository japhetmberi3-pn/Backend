<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Afficher les avis d'un produit.
     */
    public function index(Product $product): JsonResponse
    {
        $reviews = Review::with('user:id,name')
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        $average = Review::where('product_id', $product->id)
            ->avg('rating');

        return response()->json([
            'reviews' => $reviews,
            'average' => round($average ?? 0, 1),
            'count' => $reviews->count(),
        ]);
    }

    /**
     * Ajouter un avis à un produit.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $existingReview = Review::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($existingReview) {
            return response()->json([
                'message' => 'Vous avez déjà donné un avis sur ce produit.',
            ], 422);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $review->load('user:id,name');

        return response()->json([
            'message' => 'Votre avis a été ajouté avec succès.',
            'review' => $review,
        ], 201);
    }
}