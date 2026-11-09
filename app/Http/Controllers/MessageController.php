<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\MessageDeleted;

class MessageController extends Controller
{
    /**
     * Vérifie que l'utilisateur connecté
     * participe bien à la conversation.
     */
    private function isParticipant(
        Conversation $conversation,
        int $userId
    ): bool {
        return $conversation
            ->participants()
            ->where('users.id', $userId)
            ->exists();
    }

    /**
     * Récupère l'autre participant de la conversation.
     *
     * Une conversation ShopX contient actuellement
     * exactement deux participants.
     */
    private function getOtherParticipant(
        Conversation $conversation,
        int $userId
    ) {
        return $conversation
            ->participants()
            ->where('users.id', '!=', $userId)
            ->first();
    }

    /**
     * Afficher les messages d'une conversation.
     *
     * Si l'utilisateur a supprimé la conversation dans le passé,
     * il ne voit que les messages envoyés après son restart_at.
     *
     * L'autre utilisateur conserve tout son historique.
     */
    public function index(
        Request $request,
        Conversation $conversation
    ) {
        $userId = $request->user()->id;

        /*
         * Vérifier que l'utilisateur participe
         * à cette conversation.
         */
        if (!$this->isParticipant($conversation, $userId)) {
            return response()->json([
                'message' =>
                    'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Récupérer le point à partir duquel cet utilisateur
         * doit revoir les messages.
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
         * Préparer la requête des messages.
         */
        $query = Message::where(
            'conversation_id',
            $conversation->id
        )
            ->with('sender');

        /*
         * Si l'utilisateur avait supprimé la conversation,
         * ne montrer que les nouveaux messages.
         */
        if ($restartAt) {
            $query->where(
                'created_at',
                '>',
                $restartAt
            );
        }

        $messages = $query
            ->oldest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Envoyer un nouveau message.
     */
    public function store(
        Request $request,
        Conversation $conversation
    ) {
        $userId = $request->user()->id;

        /*
         * Vérifier que l'utilisateur participe
         * à la conversation.
         */
        if (!$this->isParticipant($conversation, $userId)) {
            return response()->json([
                'message' =>
                    'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Vérifier le contenu du message.
         */
        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:5000'
            ],
        ]);

        /*
         * Identifier l'autre participant.
         *
         * Cela fonctionne maintenant pour :
         *
         * Client  ↔ Admin
         * Client  ↔ Vendeur
         * Vendeur ↔ Admin
         */
        $otherUser = $this->getOtherParticipant(
            $conversation,
            $userId
        );

        if (!$otherUser) {
            return response()->json([
                'message' =>
                    'Le participant destinataire est introuvable.'
            ], 422);
        }

        /*
         * Créer le nouveau message.
         */
        $message = Message::create([
            'conversation_id' =>
                $conversation->id,

            'sender_id' =>
                $userId,

            'message' =>
                $validated['message'],
        ]);

        /*
         * Le nouveau message fait réapparaître
         * la conversation chez l'autre participant.
         *
         * IMPORTANT :
         * on efface uniquement deleted_at.
         *
         * restart_at reste inchangé.
         */
        DB::table('conversation_user')
            ->where(
                'conversation_id',
                $conversation->id
            )
            ->where(
                'user_id',
                $otherUser->id
            )
            ->whereNotNull('deleted_at')
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        /*
         * Si l'expéditeur avait lui-même supprimé
         * la conversation auparavant, on la réactive aussi.
         *
         * restart_at reste inchangé.
         */
        DB::table('conversation_user')
            ->where(
                'conversation_id',
                $conversation->id
            )
            ->where(
                'user_id',
                $userId
            )
            ->whereNotNull('deleted_at')
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        /*
         * Charger l'expéditeur.
         */
        $message->load('sender');

        /*
         * Envoyer le message en temps réel.
         */
        broadcast(
            new MessageSent($message)
        );

        return response()->json([
            'message' =>
                'Message envoyé avec succès.',

            'data' =>
                $message
        ], 201);
    }

    /**
     * Marquer un seul message comme lu.
     */
    public function markAsRead(
        Request $request,
        Message $message
    ) {
        $userId = $request->user()->id;

        /*
         * Récupérer la conversation du message.
         */
        $conversation = $message->conversation;

        /*
         * Vérifier que l'utilisateur participe
         * à la conversation.
         */
        if (!$this->isParticipant($conversation, $userId)) {
            return response()->json([
                'message' =>
                    'Vous n’avez pas accès à ce message.'
            ], 403);
        }

        /*
         * L'expéditeur ne peut pas marquer
         * son propre message comme lu.
         */
        if ($message->sender_id === $userId) {
            return response()->json([
                'message' =>
                    'Vous ne pouvez pas marquer votre propre message comme lu.'
            ], 403);
        }

        /*
         * Marquer le message comme lu.
         */
        $message->update([
            'read_at' => now(),
        ]);

        /*
         * Informer l'autre utilisateur en temps réel.
         */
        broadcast(
            new MessageRead($message)
        );

        return response()->json([
            'message' =>
                'Message marqué comme lu.',

            'data' =>
                $message
        ]);
    }

    /**
     * Marquer tous les messages reçus
     * d'une conversation comme lus.
     */
    public function markConversationAsRead(
        Request $request,
        Conversation $conversation
    ) {
        $userId = $request->user()->id;

        /*
         * Vérifier que l'utilisateur participe
         * à la conversation.
         */
        if (!$this->isParticipant($conversation, $userId)) {
            return response()->json([
                'message' =>
                    'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Récupérer les messages non lus
         * envoyés par les autres participants.
         */
        $messages = Message::where(
            'conversation_id',
            $conversation->id
        )
            ->where(
                'sender_id',
                '!=',
                $userId
            )
            ->whereNull('read_at')
            ->get();

        /*
         * Marquer chaque message comme lu.
         */
        foreach ($messages as $message) {

            $message->update([
                'read_at' => now(),
            ]);

            /*
             * Diffuser le changement en temps réel.
             */
            broadcast(
                new MessageRead($message)
            );
        }

        return response()->json([
            'message' =>
                'Tous les messages ont été marqués comme lus.',

            'count' =>
                $messages->count()
        ]);
    }

    /**
     * Supprimer un message.
     */
    public function destroy(
        Request $request,
        Message $message
    ) {
        $userId = $request->user()->id;

        /*
         * Récupérer la conversation.
         */
        $conversation = $message->conversation;

        /*
         * Vérifier que l'utilisateur participe
         * à la conversation.
         */
        if (!$this->isParticipant($conversation, $userId)) {
            return response()->json([
                'message' =>
                    'Vous n’avez pas accès à ce message.'
            ], 403);
        }

        /*
         * Un utilisateur normal ne peut supprimer
         * que ses propres messages.
         *
         * L'administrateur conserve son privilège
         * actuel de suppression.
         */
        if (
            $request->user()->role !== 'admin' &&
            $message->sender_id !== $userId
        ) {
            return response()->json([
                'message' =>
                    'Vous ne pouvez supprimer que vos propres messages.'
            ], 403);
        }

        /*
         * Garder les informations avant suppression.
         */
        $messageId =
            $message->id;

        $conversationId =
            $message->conversation_id;

        /*
         * Supprimer le message.
         */
        $message->delete();

        /*
         * Informer les utilisateurs en temps réel.
         */
        broadcast(
            new MessageDeleted(
                $messageId,
                $conversationId
            )
        );

        return response()->json([
            'message' =>
                'Message supprimé avec succès.'
        ]);
    }
}