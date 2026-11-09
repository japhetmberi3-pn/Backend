<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Models\User;
use App\Models\Message;

#[Fillable([
    'user_id',
    'admin_id',
])]
class Conversation extends Model
{
    /**
     * Ancien participant principal de la conversation.
     *
     * Cette relation est conservée pour assurer
     * la compatibilité avec les conversations actuelles.
     */
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Administrateur de la conversation.
     *
     * Conservée pour la compatibilité avec
     * le système Client ↔ Admin actuel.
     */
    public function admin()
    {
        return $this->belongsTo(
            User::class,
            'admin_id'
        );
    }

    /**
     * Tous les utilisateurs participant à la conversation.
     *
     * Cette relation permettra de gérer :
     *
     * Client ↔ Admin
     * Client ↔ Vendeur
     * Vendeur ↔ Admin
     */
    public function participants()
    {
        return $this->belongsToMany(
            User::class,
            'conversation_user'
        )
        ->withPivot([
            'deleted_at',
            'restart_at',
        ])
        ->withTimestamps();
    }

    /**
     * Messages de la conversation.
     */
    public function messages()
    {
        return $this->hasMany(
            Message::class,
            'conversation_id'
        );
    }

    /**
     * Utilisateurs pour lesquels la conversation est masquée.
     */
    public function hiddenForUsers()
    {
        return $this->belongsToMany(
            User::class,
            'conversation_user'
        )
        ->withPivot('deleted_at')
        ->withTimestamps();
    }
}