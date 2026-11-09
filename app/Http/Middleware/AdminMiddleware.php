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
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        // Par défaut : admin et vendeurs.
        // "admin:admin" limite la route aux seuls administrateurs.
        $roles = $roles ?: ['admin', 'vendeur', 'seller'];

        if (
            !$user ||
            !in_array(
                $user->role,
                $roles,
                true
            )
        ) {
            return response()->json([
                'message' => 'Accès interdit.',
            ], 403);
        }

        return $next($request);
    }
}