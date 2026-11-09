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

            // 2. Refuser les emails non vérifiés par Google :
            //    sinon on pourrait se connecter au compte d'autrui.
            $email = $googleUser->getEmail();
            $googleId = (string) $googleUser->getId();

            if (
                !$email ||
                !$googleId ||
                !filter_var(
                    $googleUser->user['email_verified'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                )
            ) {
                return $this->redirectToFrontendWithError();
            }

            // 3. Retrouver le compte (par google_id, sinon par email).
            $user = User::where('google_id', $googleId)->first()
                ?? User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName() ?: $email,
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => str()->random(64),
                ]);
            } elseif (!$user->google_id) {
                // Première liaison d'un compte existant à Google.
                // Si l'email n'avait jamais été vérifié, le compte a pu
                // être créé par quelqu'un d'autre avec cet email :
                // on invalide son mot de passe et ses sessions.
                if (!$user->email_verified_at) {
                    $user->tokens()->delete();
                    $user->password = str()->random(64);
                }

                $user->google_id = $googleId;
            } elseif ($user->google_id !== $googleId) {
                // L'email appartient déjà à un autre compte Google.
                return $this->redirectToFrontendWithError();
            }

            // Google a vérifié l'email.
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }

            $user->save();

            // 4. Générer le token Sanctum
            $token = $user
                ->createToken('auth_token')
                ->plainTextToken;

            // 5. Rediriger l'utilisateur vers Next.js
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