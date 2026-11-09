<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\User;

class ConversationController extends Controller
{
    /**
     * Vérifie si deux rôles peuvent discuter ensemble.
     *
     * Les seules combinaisons autorisées sont :
     *
     * client  ↔ admin
     * client  ↔ vendeur
     * vendeur ↔ admin
     */
    private function canCommunicate(
        string $role1,
        string $role2
    ): bool {
        $roles = [$role1, $role2];

        sort($roles);

        return in_array($roles, [
            ['admin', 'client'],
            ['admin', 'vendeur'],
            ['client', 'vendeur'],
        ], true);
    }

    /**
     * Ajoute les informations de profil à un utilisateur.
     */
    private function prepareUser(User $user): User
    {
        $user->is_online = $user->isOnline();

        $user->profile_photo_url =
            $user->profile_photo
                ? asset(
                    'storage/' .
                    $user->profile_photo
                )
                : null;

        return $user;
    }

    /**
     * Prépare les participants d'une conversation.
     */
    private function prepareConversation(
        Conversation $conversation
    ): Conversation {
        $conversation->load([
            'participants',
        ]);

        $conversation->participants->each(
            function ($participant) {
                $this->prepareUser($participant);
            }
        );

        /*
         * Compatibilité avec l'ancien frontend :
         *
         * conversation.user
         * conversation.admin
         *
         * Pour une conversation avec un vendeur,
         * on ajoute également :
         *
         * conversation.seller
         */
        $conversation->setRelation(
            'user',
            $conversation->participants
                ->firstWhere('role', 'client')
        );

        $conversation->setRelation(
            'admin',
            $conversation->participants
                ->firstWhere('role', 'admin')
        );

        $conversation->setRelation(
            'seller',
            $conversation->participants
                ->firstWhere('role', 'vendeur')
        );

        return $conversation;
    }

    /**
     * Liste les conversations de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::whereHas(
            'participants',
            function ($query) use ($userId) {
                $query->where('users.id', $userId);
            }
        )
        ->whereDoesntHave(
            'hiddenForUsers',
            function ($query) use ($userId) {
                $query->where('users.id', $userId)
                    ->whereNotNull(
                        'conversation_user.deleted_at'
                    );
            }
        )
        ->with('participants')
        ->withCount([
            'messages as unread_count' => function ($query) use ($userId) {
                $query->whereNull('read_at')
                    ->where(
                        'sender_id',
                        '!=',
                        $userId
                    );
            }
        ])
        ->latest()
        ->get();

        $conversations->each(
            function ($conversation) {
                $this->prepareConversation($conversation);
            }
        );

        return response()->json(
            $conversations
        );
    }

    /**
     * Crée une nouvelle conversation.
     *
     * Compatible avec :
     *
     * admin_id      → ancienne logique Client ↔ Admin
     * seller_id     → Client ↔ Vendeur
     * participant_id → nouvelle logique générale
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'participant_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'admin_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'seller_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        $currentUser = $request->user();

        /*
         * Déterminer l'identifiant du participant.
         *
         * participant_id est prioritaire.
         */
        $participantId =
            $validated['participant_id']
            ?? $validated['admin_id']
            ?? $validated['seller_id']
            ?? null;

        if (!$participantId) {
            return response()->json([
                'message' =>
                    'Vous devez indiquer le participant.'
            ], 422);
        }

        /*
         * Empêcher un utilisateur de démarrer
         * une conversation avec lui-même.
         */
        if ((int) $participantId === (int) $currentUser->id) {
            return response()->json([
                'message' =>
                    'Vous ne pouvez pas créer une conversation avec vous-même.'
            ], 422);
        }

        /*
         * Récupérer le participant.
         */
        $participant = User::find($participantId);

        if (!$participant) {
            return response()->json([
                'message' =>
                    'Utilisateur introuvable.'
            ], 404);
        }

        /*
         * Vérifier les rôles autorisés.
         */
        if (
            !$this->canCommunicate(
                $currentUser->role,
                $participant->role
            )
        ) {
            return response()->json([
                'message' =>
                    'Ces deux utilisateurs ne peuvent pas avoir de conversation.'
            ], 403);
        }

        /*
         * Rechercher une conversation existante
         * entre exactement ces deux participants.
         */
        $conversation = Conversation::whereHas(
            'participants',
            function ($query) use ($currentUser) {
                $query->where(
                    'users.id',
                    $currentUser->id
                );
            }
        )
        ->whereHas(
            'participants',
            function ($query) use ($participant) {
                $query->where(
                    'users.id',
                    $participant->id
                );
            }
        )
        ->get()
        ->first(
            function ($conversation) {
                return $conversation->participants()
                    ->count() === 2;
            }
        );

        /*
         * La conversation existe déjà.
         */
        if ($conversation) {

            /*
             * Réactiver la conversation pour
             * l'utilisateur qui vient de l'ouvrir.
             *
             * restart_at n'est PAS modifié.
             */
            DB::table('conversation_user')
                ->where(
                    'conversation_id',
                    $conversation->id
                )
                ->where(
                    'user_id',
                    $currentUser->id
                )
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            $conversation->load('participants');

            $this->prepareConversation(
                $conversation
            );

            return response()->json([
                'message' =>
                    'Cette conversation existe déjà.',
                'conversation' =>
                    $conversation,
            ], 200);
        }

        /*
         * Créer la conversation.
         */
        $conversation = Conversation::create([
            /*
             * On conserve les anciennes colonnes
             * pour la compatibilité avec la base actuelle.
             *
             * Elles ne sont plus utilisées comme source
             * principale des participants.
             */
            'user_id' =>
                $currentUser->role === 'client'
                    ? $currentUser->id
                    : (
                        $participant->role === 'client'
                            ? $participant->id
                            : $currentUser->id
                    ),

            'admin_id' =>
                $currentUser->role === 'admin'
                    ? $currentUser->id
                    : (
                        $participant->role === 'admin'
                            ? $participant->id
                            : $currentUser->id
                    ),
        ]);

        /*
         * Ajouter les deux participants.
         */
        DB::table('conversation_user')
            ->insert([
                [
                    'conversation_id' =>
                        $conversation->id,
                    'user_id' =>
                        $currentUser->id,
                    'deleted_at' => null,
                    'restart_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'conversation_id' =>
                        $conversation->id,
                    'user_id' =>
                        $participant->id,
                    'deleted_at' => null,
                    'restart_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

        /*
         * Charger les participants.
         */
        $conversation->load('participants');

        $this->prepareConversation(
            $conversation
        );

        return response()->json([
            'message' =>
                'Conversation créée avec succès.',
            'conversation' =>
                $conversation,
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

        /*
         * Vérifier que l'utilisateur participe
         * réellement à la conversation.
         */
        $isParticipant = $conversation
            ->participants()
            ->where(
                'users.id',
                $userId
            )
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'message' =>
                    'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Vérifier si la conversation est supprimée
         * pour cet utilisateur.
         */
        $hidden = DB::table('conversation_user')
            ->where(
                'conversation_id',
                $conversation->id
            )
            ->where(
                'user_id',
                $userId
            )
            ->whereNotNull('deleted_at')
            ->exists();

        if ($hidden) {
            return response()->json([
                'message' =>
                    'Cette conversation a été supprimée.'
            ], 404);
        }

        /*
         * Récupérer le point de redémarrage
         * propre à cet utilisateur.
         */
        $restartAt = DB::table('conversation_user')
            ->where(
                'conversation_id',
                $conversation->id
            )
            ->where(
                'user_id',
                $userId
            )
            ->value('restart_at');

        /*
         * Charger les participants.
         */
        $this->prepareConversation(
            $conversation
        );

        /*
         * Charger les messages.
         */
        $messagesQuery = $conversation
            ->messages()
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
            $messagesQuery
                ->oldest()
                ->get()
        );

        return response()->json(
            $conversation
        );
    }

    /**
     * Retourne la liste des administrateurs.
     */
    public function admins()
    {
        $admins = User::where(
            'role',
            'admin'
        )
            ->select(
                'id',
                'name',
                'email',
                'role',
                'profile_photo'
            )
            ->get();

        $admins->each(
            function ($admin) {
                $admin->profile_photo_url =
                    $admin->profile_photo
                        ? asset(
                            'storage/' .
                            $admin->profile_photo
                        )
                        : null;
            }
        );

        return response()->json(
            $admins
        );
    }

    /**
     * Retourne la liste des vendeurs.
     */
    public function sellers()
    {
        $sellers = User::where(
            'role',
            'vendeur'
        )
            ->select(
                'id',
                'name',
                'email',
                'role',
                'profile_photo'
            )
            ->get();

        $sellers->each(
            function ($seller) {
                $seller->profile_photo_url =
                    $seller->profile_photo
                        ? asset(
                            'storage/' .
                            $seller->profile_photo
                        )
                        : null;
            }
        );

        return response()->json(
            $sellers
        );
    }

    /**
     * Masque la conversation uniquement
     * pour l'utilisateur connecté.
     *
     * Les anciens messages restent en base.
     */
    public function destroy(
        Request $request,
        Conversation $conversation
    ) {
        $userId = $request->user()->id;

        /*
         * Vérifier que l'utilisateur participe
         * à la conversation.
         */
        $isParticipant = $conversation
            ->participants()
            ->where(
                'users.id',
                $userId
            )
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'message' =>
                    'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Vérifier si une ligne existe déjà.
         */
        $existing = DB::table('conversation_user')
            ->where(
                'conversation_id',
                $conversation->id
            )
            ->where(
                'user_id',
                $userId
            )
            ->first();

        if ($existing) {

            /*
             * Masquer la conversation et mémoriser
             * le moment où l'ancien historique s'arrête.
             */
            DB::table('conversation_user')
                ->where(
                    'id',
                    $existing->id
                )
                ->update([
                    'deleted_at' => now(),
                    'restart_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {

            /*
             * Sécurité supplémentaire.
             */
            DB::table('conversation_user')
                ->insert([
                    'conversation_id' =>
                        $conversation->id,
                    'user_id' =>
                        $userId,
                    'deleted_at' => now(),
                    'restart_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'message' =>
                'Conversation supprimée pour vous uniquement.'
        ]);
    }
}