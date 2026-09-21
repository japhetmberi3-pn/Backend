<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Mettre à jour la photo de profil de l'utilisateur connecté.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        // Validation de la photo
        $request->validate([
            'profile_photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048',
            ],
        ]);

        // Récupérer l'utilisateur connecté
        $user = $request->user();

        // Supprimer l'ancienne photo si elle existe
        if ($user->profile_photo) {
            Storage::disk('public')->delete(
                $user->profile_photo
            );
        }

        // Enregistrer la nouvelle photo
        $path = $request->file('profile_photo')
            ->store('profile-photos', 'public');

        // Enregistrer le chemin dans la base de données
        $user->update([
            'profile_photo' => $path,
        ]);

        // Retourner l'utilisateur mis à jour
        return response()->json([
            'message' => 'Photo de profil mise à jour avec succès.',
            'profile_photo' => $path,
            'profile_photo_url' => url(
                Storage::url($path)
            ),
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Supprimer la photo de profil de l'utilisateur connecté.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        // Récupérer l'utilisateur connecté
        $user = $request->user();

        // Supprimer le fichier physique s'il existe
        if ($user->profile_photo) {
            Storage::disk('public')->delete(
                $user->profile_photo
            );
        }

        // Retirer la photo de la base de données
        $user->update([
            'profile_photo' => null,
        ]);

        // Retourner l'utilisateur mis à jour
        return response()->json([
            'message' => 'Photo de profil supprimée avec succès.',
            'profile_photo' => null,
            'profile_photo_url' => null,
            'user' => $user->fresh(),
        ]);
    }
}