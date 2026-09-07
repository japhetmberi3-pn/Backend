<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirige l'utilisateur vers la page de connexion Google.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * Gère le retour de Google après l'authentification.
     */
    public function callback(): RedirectResponse
    {
        try {
            // 1. Récupérer les informations de l'utilisateur Google
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            // 2. Créer le compte ShopX ou mettre à jour
            //    le compte existant correspondant à l'email.
            $user = User::updateOrCreate(
                [
                    'email' => $googleUser->getEmail(),
                ],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'password' => str()->random(32),
                ]
            );

            // 3. Générer le token Sanctum
            $token = $user
                ->createToken('auth_token')
                ->plainTextToken;

            // 4. Rediriger l'utilisateur vers Next.js
            return $this->redirectToFrontend($token);

        } catch (\Throwable $exception) {
            // En cas d'erreur, retourner vers Next.js
            return $this->redirectToFrontendWithError();
        }
    }

    /**
     * Redirige l'utilisateur vers la page callback de Next.js
     * avec le token d'authentification.
     */
    private function redirectToFrontend(string $token): RedirectResponse
    {
        $frontendUrl = config('services.frontend.url');

        return redirect()->away(
            $frontendUrl
            . '/auth/google/callback#token='
            . urlencode($token)
        );
    }

    /**
     * Redirige vers Next.js lorsqu'une erreur survient.
     */
    private function redirectToFrontendWithError(): RedirectResponse
    {
        $frontendUrl = config('services.frontend.url');

        return redirect()->away(
            $frontendUrl
            . '/auth/google/callback?error='
            . urlencode(
                'La connexion avec Google a échoué.'
            )
        );
    }
}