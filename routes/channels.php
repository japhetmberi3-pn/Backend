<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canal privé de l'utilisateur
|--------------------------------------------------------------------------
*/

Broadcast::channel(
    'App.Models.User.{id}',
    function ($user, $id) {
        return (int) $user->id === (int) $id;
    }
);


/*
|--------------------------------------------------------------------------
| Canal privé d'une conversation
|--------------------------------------------------------------------------
|
| Une conversation peut maintenant être :
|
| Client  ↔ Admin
| Client  ↔ Vendeur
| Vendeur ↔ Admin
|
| On vérifie donc la présence de l'utilisateur
| dans conversation_user.
|
*/

Broadcast::channel(
    'conversation.{conversationId}',
    function ($user, $conversationId) {

        $conversation = Conversation::find(
            $conversationId
        );

        /*
         * Conversation inexistante.
         */
        if (!$conversation) {
            return false;
        }

        /*
         * Vérifier que l'utilisateur connecté
         * est bien l'un des participants.
         */
        return $conversation
            ->participants()
            ->where(
                'users.id',
                $user->id
            )
            ->exists();
    }
);


/*
|--------------------------------------------------------------------------
| Canal de présence pour savoir qui est connecté
|--------------------------------------------------------------------------
*/

Broadcast::channel(
    'presence.chat',
    function ($user) {

        return [
            'id' =>
                $user->id,

            'name' =>
                $user->name,
        ];
    }
);