<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Models\Conversation;
use App\Models\User;

#[Fillable([
    'conversation_id',
    'sender_id',
    'message',
    'read_at',
])]
class Message extends Model
{
    // Le message appartient à une conversation
    public function conversation()
    {
        return $this->belongsTo(
            Conversation::class,
            'conversation_id'
        );
    }

    // Le message appartient à l'utilisateur qui l'a envoyé
    public function sender()
    {
        return $this->belongsTo(
            User::class,
            'sender_id'
        );
    }
}