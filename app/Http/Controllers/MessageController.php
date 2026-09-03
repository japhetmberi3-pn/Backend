<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\MessageReceivedNotification;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\MessageDeleted;

class MessageController extends Controller
{
    // Récupérer les messages d'une conversation
    public function index(Request $request, Conversation $conversation)
    {
        if (
            $conversation->user_id !== $request->user()->id &&
            $conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->with('sender')
            ->oldest()
            ->get();

        return response()->json($messages);
    }

    // Envoyer un message
    public function store(Request $request, Conversation $conversation)
    {
        if (
            $conversation->user_id !== $request->user()->id &&
            $conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Créer le message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        // Charger l'expéditeur
        $message->load('sender');

        // Diffuser le nouveau message en temps réel
        broadcast(new MessageSent($message));

        // Déterminer le destinataire
        if ($conversation->user_id === $request->user()->id) {
            $recipient = $conversation->admin;
        } else {
            $recipient = $conversation->user;
        }

        // Envoyer une notification au destinataire
        $recipient->notify(
            new MessageReceivedNotification($message)
        );

        return response()->json([
            'message' => 'Message envoyé avec succès.',
            'data' => $message
        ], 201);
    }

    // Marquer un message comme lu
    public function markAsRead(Request $request, Message $message)
    {
        // Vérifier que l'utilisateur appartient à la conversation
        if (
            $message->conversation->user_id !== $request->user()->id &&
            $message->conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à ce message.'
            ], 403);
        }

        // Vérifier que le message vient de l'autre participant
        if ($message->sender_id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas marquer votre propre message comme lu.'
            ], 403);
        }

        // Marquer le message comme lu
        $message->update([
            'read_at' => now(),
        ]);

        // Diffuser l'événement de lecture du message
        broadcast(new MessageRead($message));

        // Marquer également la notification correspondante comme lue
        $notification = $request->user()
            ->notifications()
            ->where('type', MessageReceivedNotification::class)
            ->whereJsonContains('data->message_id', $message->id)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Message et notification marqués comme lus.',
            'data' => $message
        ]);
    }
        
    // Supprimer un message
    public function destroy(Request $request, Message $message)
    {
        // Vérifier que l'utilisateur appartient à la conversation
        if (
            $message->conversation->user_id !== $request->user()->id &&
            $message->conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à ce message.'
            ], 403);
        }

        // L'utilisateur normal ne peut supprimer que ses propres messages
        if (
            $request->user()->role !== 'admin' &&
            $message->sender_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous ne pouvez supprimer que vos propres messages.'
            ], 403);
        }

        // Supprimer la notification liée au message
        $users = [$message->sender_id];

        if ($message->conversation->user_id !== $message->sender_id) {
            $users[] = $message->conversation->user_id;
        }

        if ($message->conversation->admin_id !== $message->sender_id) {
            $users[] = $message->conversation->admin_id;
        }

        foreach (array_unique($users) as $userId) {
            \Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $userId)
                ->where('notifiable_type', 'App\Models\User')
                ->where('type', MessageReceivedNotification::class)
                ->whereJsonContains('data->message_id', $message->id)
                ->delete();
        }

        // Garder les identifiants avant la suppression
        $messageId = $message->id;
        $conversationId = $message->conversation_id;

        // Supprimer le message
        $message->delete();

        // Diffuser la suppression en temps réel
        broadcast(new MessageDeleted(
            $messageId,
            $conversationId
        ));

        return response()->json([
            'message' => 'Message et notification supprimés avec succès.'
        ]);
    }

}