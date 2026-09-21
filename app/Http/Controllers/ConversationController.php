<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\User;

class ConversationController extends Controller
{
    /**
     * Liste les conversations de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->orWhere('admin_id', $userId);
        })
        ->whereDoesntHave('hiddenForUsers', function ($query) use ($userId) {
            $query->where('users.id', $userId)
                ->whereNotNull('conversation_user.deleted_at');
        })
        ->with([
            'user',
            'admin',
        ])
        ->withCount([
            'messages as unread_count' => function ($query) use ($userId) {
                $query->whereNull('read_at')
                    ->where('sender_id', '!=', $userId);
            }
        ])
        ->latest()
        ->get();

        /*
         * Ajouter les informations nécessaires au profil
         * de chaque participant.
         */
        $conversations->each(function ($conversation) {

            if ($conversation->user) {
                $conversation->user->is_online =
                    $conversation->user->isOnline();

                /*
                 * S'assurer que l'URL de la photo est disponible
                 * dans la réponse JSON.
                 */
                $conversation->user->profile_photo_url =
                    $conversation->user->profile_photo
                        ? asset(
                            'storage/' .
                            $conversation->user->profile_photo
                        )
                        : null;
            }

            if ($conversation->admin) {
                $conversation->admin->is_online =
                    $conversation->admin->isOnline();

                /*
                 * Même chose pour l'administrateur.
                 */
                $conversation->admin->profile_photo_url =
                    $conversation->admin->profile_photo
                        ? asset(
                            'storage/' .
                            $conversation->admin->profile_photo
                        )
                        : null;
            }
        });

        return response()->json($conversations);
    }

    /**
     * Crée une nouvelle conversation avec un administrateur.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'admin_id' => [
                'required',
                'integer',
                'exists:users,id'
            ],
        ]);

        $admin = User::where('id', $validated['admin_id'])
            ->where('role', 'admin')
            ->first();

        if (!$admin) {
            return response()->json([
                'message' => 'L’utilisateur choisi n’est pas un administrateur.'
            ], 403);
        }

        $userId = $request->user()->id;

        $conversation = Conversation::where(
            'user_id',
            $userId
        )
            ->where('admin_id', $admin->id)
            ->first();

        if ($conversation) {

            /*
             * La conversation existe déjà.
             *
             * On la rend visible pour l'utilisateur,
             * mais on NE supprime PAS restart_at.
             */
            DB::table('conversation_user')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            /*
             * Recharger les utilisateurs avec leurs profils.
             */
            $conversation->load([
                'user',
                'admin'
            ]);

            if ($conversation->user) {
                $conversation->user->is_online =
                    $conversation->user->isOnline();

                $conversation->user->profile_photo_url =
                    $conversation->user->profile_photo
                        ? asset(
                            'storage/' .
                            $conversation->user->profile_photo
                        )
                        : null;
            }

            if ($conversation->admin) {
                $conversation->admin->is_online =
                    $conversation->admin->isOnline();

                $conversation->admin->profile_photo_url =
                    $conversation->admin->profile_photo
                        ? asset(
                            'storage/' .
                            $conversation->admin->profile_photo
                        )
                        : null;
            }

            return response()->json([
                'message' => 'Cette conversation existe déjà.',
                'conversation' => $conversation
            ], 200);
        }

        $conversation = Conversation::create([
            'user_id' => $userId,
            'admin_id' => $admin->id,
        ]);

        /*
         * Charger les profils immédiatement.
         */
        $conversation->load([
            'user',
            'admin'
        ]);

        if ($conversation->user) {
            $conversation->user->is_online =
                $conversation->user->isOnline();

            $conversation->user->profile_photo_url =
                $conversation->user->profile_photo
                    ? asset(
                        'storage/' .
                        $conversation->user->profile_photo
                    )
                    : null;
        }

        if ($conversation->admin) {
            $conversation->admin->is_online =
                $conversation->admin->isOnline();

            $conversation->admin->profile_photo_url =
                $conversation->admin->profile_photo
                    ? asset(
                        'storage/' .
                        $conversation->admin->profile_photo
                    )
                    : null;
        }

        return response()->json([
            'message' => 'Conversation créée avec succès.',
            'conversation' => $conversation
        ], 201);
    }

    /**
     * Affiche une conversation avec ses messages.
     */
    public function show(
        Request $request,
        Conversation $conversation
    ) {
        $userId = $request->user()->id;

        if (
            $conversation->user_id !== $userId &&
            $conversation->admin_id !== $userId
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Vérifier si la conversation est supprimée
         * pour cet utilisateur.
         */
        $hidden = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->whereNotNull('deleted_at')
            ->exists();

        if ($hidden) {
            return response()->json([
                'message' => 'Cette conversation a été supprimée.'
            ], 404);
        }

        /*
         * Récupérer le point de redémarrage propre
         * à cet utilisateur.
         */
        $restartAt = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->value('restart_at');

        $conversation->load([
            'user',
            'admin'
        ]);

        /*
         * Ajouter les informations de profil.
         */
        if ($conversation->user) {
            $conversation->user->is_online =
                $conversation->user->isOnline();

            $conversation->user->profile_photo_url =
                $conversation->user->profile_photo
                    ? asset(
                        'storage/' .
                        $conversation->user->profile_photo
                    )
                    : null;
        }

        if ($conversation->admin) {
            $conversation->admin->is_online =
                $conversation->admin->isOnline();

            $conversation->admin->profile_photo_url =
                $conversation->admin->profile_photo
                    ? asset(
                        'storage/' .
                        $conversation->admin->profile_photo
                    )
                    : null;
        }

        /*
         * Charger les messages.
         */
        $messagesQuery = $conversation->messages()
            ->with('sender');

        if ($restartAt) {
            $messagesQuery->where(
                'created_at',
                '>',
                $restartAt
            );
        }

        $conversation->setRelation(
            'messages',
            $messagesQuery->oldest()->get()
        );

        return response()->json($conversation);
    }

    /**
     * Retourne la liste des administrateurs.
     */
    public function admins()
    {
        $admins = User::where('role', 'admin')
            ->select(
                'id',
                'name',
                'email',
                'role',
                'profile_photo'
            )
            ->get();

        /*
         * Ajouter l'URL publique de la photo.
         */
        $admins->each(function ($admin) {
            $admin->profile_photo_url =
                $admin->profile_photo
                    ? asset(
                        'storage/' .
                        $admin->profile_photo
                    )
                    : null;
        });

        return response()->json($admins);
    }

    /**
     * Masque la conversation uniquement pour l'utilisateur connecté.
     *
     * Les anciens messages restent en base.
     */
    public function destroy(
        Request $request,
        Conversation $conversation
    ) {
        $userId = $request->user()->id;

        if (
            $conversation->user_id !== $userId &&
            $conversation->admin_id !== $userId
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Vérifier si une ligne existe déjà pour cet utilisateur.
         */
        $existing = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {

            /*
             * On cache la conversation ET on mémorise
             * le moment où l'ancien historique doit s'arrêter.
             */
            DB::table('conversation_user')
                ->where('id', $existing->id)
                ->update([
                    'deleted_at' => now(),
                    'restart_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {

            /*
             * Première suppression de cette conversation
             * pour cet utilisateur.
             */
            DB::table('conversation_user')
                ->insert([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'deleted_at' => now(),
                    'restart_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'message' => 'Conversation supprimée pour vous uniquement.'
        ]);
    }
}