<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductPurchasedNotification extends Notification
{
    use Queueable;

    /**
     * Créer une nouvelle notification.
     */
    public function __construct(
        public int $productId,
        public string $productName,
        public int $quantity,
        public string $clientName
    ) {
    }

    /**
     * Canaux utilisés pour la notification.
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
        $message = $notifiable->role === 'admin'
            ? $this->clientName . ' a acheté le produit "' .
                $this->productName . '".'
            : 'Vous avez acheté le produit "' .
                $this->productName .
                '" avec succès.';

        return [
            'type' => 'product_purchased',
            'message' => $message,
            'client_name' => $this->clientName,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
        ];
    }
}