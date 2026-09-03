<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privé d'une conversation
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        return false;
    }

    return (int) $user->id === (int) $conversation->user_id
        || (int) $user->id === (int) $conversation->admin_id;
});

// Canal de présence pour savoir qui est connecté
Broadcast::channel('presence.chat', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
