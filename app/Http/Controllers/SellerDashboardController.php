<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SellerDashboardController extends Controller
{
    /**
     * Statistiques du vendeur connecté.
     */
    public function dashboard(): JsonResponse
    {
        $seller = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | PRODUITS DU VENDEUR
        |--------------------------------------------------------------------------
        */

        $produits = Product::where('user_id', $seller->id)->count();

        /*
        |--------------------------------------------------------------------------
        | COMMANDES ET CHIFFRE D'AFFAIRES
        |--------------------------------------------------------------------------
        |
        | Un vendeur ne possède pas directement les commandes.
        | Les produits vendus dans les commandes appartiennent au vendeur.
        |
        */

        $orderItems = OrderItem::whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        });

        $commandes = (clone $orderItems)
            ->select('order_id')
            ->distinct()
            ->count('order_id');

        $chiffreAffaires = (clone $orderItems)
            ->selectRaw(
                'COALESCE(SUM(quantity * price), 0) as total'
            )
            ->value('total');

        /*
        |--------------------------------------------------------------------------
        | STATISTIQUES DES 6 DERNIERS MOIS
        |--------------------------------------------------------------------------
        */

        $ventesMensuelles = [];

        $commandesMensuelles = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()
                ->subMonths($i)
                ->startOfMonth();

            $finDuMois = $date->copy()->endOfMonth();

            /*
            |--------------------------------------------------------------------------
            | CHIFFRE D'AFFAIRES DU MOIS
            |--------------------------------------------------------------------------
            */

            $ca = OrderItem::whereHas('product', function ($query) use ($seller) {
                $query->where('user_id', $seller->id);
            })
                ->whereHas('order', function ($query) use ($date, $finDuMois) {
                    $query->whereBetween('created_at', [
                        $date,
                        $finDuMois,
                    ]);
                })
                ->selectRaw(
                    'COALESCE(SUM(quantity * price), 0) as total'
                )
                ->value('total');

            /*
            |--------------------------------------------------------------------------
            | COMMANDES DU MOIS
            |--------------------------------------------------------------------------
            */

            $nombreCommandes = OrderItem::whereHas('product', function ($query) use ($seller) {
                $query->where('user_id', $seller->id);
            })
                ->whereHas('order', function ($query) use ($date, $finDuMois) {
                    $query->whereBetween('created_at', [
                        $date,
                        $finDuMois,
                    ]);
                })
                ->select('order_id')
                ->distinct()
                ->count('order_id');

            $ventesMensuelles[] = [
                'mois' => $date->translatedFormat('M'),
                'ca' => (float) $ca,
            ];

            $commandesMensuelles[] = [
                'mois' => $date->translatedFormat('M'),
                'commandes' => $nombreCommandes,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | PRODUITS LES PLUS VENDUS
        |--------------------------------------------------------------------------
        */

        $topProduits = OrderItem::whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })
            ->with('product:id,name')
            ->selectRaw(
                'product_id, SUM(quantity) as quantite_vendue'
            )
            ->groupBy('product_id')
            ->orderByDesc('quantite_vendue')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'nom' => $item->product?->name,
                    'quantite_vendue' => (int) $item->quantite_vendue,
                ];
            })
            ->values();

        return response()->json([
            'vendeur' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
            ],

            'produits' => $produits,

            'commandes' => $commandes,

            'chiffre_affaires' => (float) $chiffreAffaires,

            'ventes_mensuelles' => $ventesMensuelles,

            'commandes_mensuelles' => $commandesMensuelles,

            'top_produits' => $topProduits,
        ]);
    }
}