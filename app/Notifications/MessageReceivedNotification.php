<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MessageReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Message $message
    ) {
    }

    /**
     * Canaux utilisés par la notification.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Données enregistrées dans la table notifications.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'message_received',
            'message' => 'Vous avez reçu un nouveau message.',
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
        ];
    }
}