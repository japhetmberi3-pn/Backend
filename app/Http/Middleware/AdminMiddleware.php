<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (
            !$user ||
            !in_array(
                $user->role,
                ['admin', 'vendeur', 'seller']
            )
        ) {
            return response()->json([
                'message' => 'Accès interdit. Réservé aux administrateurs et aux vendeurs.',
            ], 403);
        }

        return $next($request);
    }
}