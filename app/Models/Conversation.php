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
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function admin()
    {
        return $this->belongsTo(
            User::class,
            'admin_id'
        );
    }

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