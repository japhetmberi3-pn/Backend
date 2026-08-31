<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Notifications\ProductPurchasedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);

            if ($product->stock < $validated['quantity']) {
                abort(422, 'Stock insuffisant.');
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'status' => 'completed',
            ]);

            $product->decrement('stock', $validated['quantity']);

            $request->user()->notify(
                new ProductPurchasedNotification(
                    $product->id,
                    $product->name,
                    $validated['quantity']
                )
            );

            return $order;
        });

        return response()->json([
            'message' => 'Achat effectué avec succès.',
            'order' => $order->load('product'),
        ], 201);
    }
}