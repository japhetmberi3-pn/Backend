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
     * Afficher les messages d'une conversation.
     *
     * Si l'utilisateur a supprimé la conversation dans le passé,
     * il ne voit que les messages envoyés après son restart_at.
     *
     * L'autre utilisateur conserve tout l'historique.
     */
    public function index(
        Request $request,
        Conversation $conversation
    ) {
        $userId = $request->user()->id;

        // Vérifier que l'utilisateur appartient à la conversation
        if (
            $conversation->user_id !== $userId &&
            $conversation->admin_id !== $userId
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        /*
         * Récupérer le point à partir duquel cet utilisateur
         * doit revoir les messages.
         */
        $restartAt = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->value('restart_at');

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

        // Vérifier que l'utilisateur appartient à la conversation
        if (
            $conversation->user_id !== $userId &&
            $conversation->admin_id !== $userId
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        // Vérifier le contenu du message
        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:5000'
            ],
        ]);

        /*
         * Identifier l'autre participant.
         */
        $otherUserId =
            $conversation->user_id === $userId
                ? $conversation->admin_id
                : $conversation->user_id;

        /*
         * Créer le nouveau message.
         */
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'message' => $validated['message'],
        ]);

        /*
         * Le nouveau message fait réapparaître
         * la conversation chez l'autre participant.
         *
         * IMPORTANT :
         * on efface uniquement deleted_at.
         *
         * On NE touche PAS à restart_at.
         *
         * Ainsi :
         * - la conversation réapparaît ;
         * - les anciens messages restent masqués
         *   chez celui qui avait supprimé la conversation.
         */
        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $otherUserId)
            ->whereNotNull('deleted_at')
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        /*
         * Si l'expéditeur avait lui-même supprimé
         * la conversation auparavant, on la réactive aussi
         * pour lui.
         *
         * restart_at reste inchangé.
         */
        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
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
        broadcast(new MessageSent($message));

        return response()->json([
            'message' => 'Message envoyé avec succès.',
            'data' => $message
        ], 201);
    }

    /**
     * Marquer un seul message comme lu.
     */
    public function markAsRead(
        Request $request,
        Message $message
    ) {
        // Vérifier que l'utilisateur appartient à la conversation
        if (
            $message->conversation->user_id !== $request->user()->id &&
            $message->conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à ce message.'
            ], 403);
        }

        // L'expéditeur ne peut pas marquer son propre message comme lu
        if ($message->sender_id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas marquer votre propre message comme lu.'
            ], 403);
        }

        // Marquer le message comme lu
        $message->update([
            'read_at' => now(),
        ]);

        // Informer l'autre utilisateur en temps réel
        broadcast(new MessageRead($message));

        return response()->json([
            'message' => 'Message marqué comme lu.',
            'data' => $message
        ]);
    }

    /**
     * Marquer tous les messages reçus d'une conversation comme lus.
     */
    public function markConversationAsRead(
        Request $request,
        Conversation $conversation
    ) {
        // Vérifier que l'utilisateur appartient à la conversation
        if (
            $conversation->user_id !== $request->user()->id &&
            $conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        // Récupérer les messages non lus envoyés par l'autre utilisateur
        $messages = Message::where(
            'conversation_id',
            $conversation->id
        )
            ->where(
                'sender_id',
                '!=',
                $request->user()->id
            )
            ->whereNull('read_at')
            ->get();

        // Marquer chaque message comme lu
        foreach ($messages as $message) {
            $message->update([
                'read_at' => now(),
            ]);

            // Diffuser le changement en temps réel
            broadcast(new MessageRead($message));
        }

        return response()->json([
            'message' => 'Tous les messages ont été marqués comme lus.',
            'count' => $messages->count()
        ]);
    }

    /**
     * Supprimer un message.
     */
    public function destroy(
        Request $request,
        Message $message
    ) {
        // Vérifier que l'utilisateur appartient à la conversation
        if (
            $message->conversation->user_id !== $request->user()->id &&
            $message->conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à ce message.'
            ], 403);
        }

        // Un utilisateur normal ne peut supprimer
        // que ses propres messages.
        if (
            $request->user()->role !== 'admin' &&
            $message->sender_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous ne pouvez supprimer que vos propres messages.'
            ], 403);
        }

        // Garder les informations avant suppression
        $messageId = $message->id;
        $conversationId = $message->conversation_id;

        // Supprimer le message
        $message->delete();

        // Informer les utilisateurs en temps réel
        broadcast(new MessageDeleted(
            $messageId,
            $conversationId
        ));

        return response()->json([
            'message' => 'Message supprimé avec succès.'
        ]);
    }
}