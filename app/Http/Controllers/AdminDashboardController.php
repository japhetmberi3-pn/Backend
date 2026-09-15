<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Statistiques générales du Dashboard Admin.
     */
    public function dashboard(): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | STATISTIQUES GÉNÉRALES
        |--------------------------------------------------------------------------
        */

        $clients = User::where('role', 'client')->count();

        $vendeurs = User::where('role', 'vendeur')->count();

        $admins = User::where('role', 'admin')->count();

        $produits = Product::count();

        $commandes = Order::count();

        /*
        |--------------------------------------------------------------------------
        | CHIFFRE D'AFFAIRES TOTAL
        |--------------------------------------------------------------------------
        */

        $chiffreAffaires = OrderItem::selectRaw(
            'COALESCE(SUM(quantity * price), 0) as total'
        )->value('total');

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

            $ca = OrderItem::whereHas('order', function ($query) use (
                $date,
                $finDuMois
            ) {
                $query
                    ->whereBetween('created_at', [
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
            | NOMBRE DE COMMANDES DU MOIS
            |--------------------------------------------------------------------------
            */

            $nombreCommandes = Order::whereBetween('created_at', [
                $date,
                $finDuMois,
            ])->count();

            /*
            |--------------------------------------------------------------------------
            | DONNÉES POUR LE FRONTEND
            |--------------------------------------------------------------------------
            */

            $ventesMensuelles[] = [
                'mois' => $date->translatedFormat('M'),
                'ca' => (float) $ca,
            ];

            $commandesMensuelles[] = [
                'mois' => $date->translatedFormat('M'),
                'commandes' => $nombreCommandes,
            ];
        }

        return response()->json([
            /*
            |--------------------------------------------------------------------------
            | TOTAUX
            |--------------------------------------------------------------------------
            */

            'clients' => $clients,
            'vendeurs' => $vendeurs,
            'admins' => $admins,
            'produits' => $produits,
            'commandes' => $commandes,
            'chiffre_affaires' => (float) $chiffreAffaires,

            /*
            |--------------------------------------------------------------------------
            | STATISTIQUES
            |--------------------------------------------------------------------------
            */

            'ventes_mensuelles' => $ventesMensuelles,

            'commandes_mensuelles' => $commandesMensuelles,
        ]);
    }
}