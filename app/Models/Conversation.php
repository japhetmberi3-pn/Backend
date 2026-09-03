<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Models\User;
use App\Models\Message;

#[Fillable(['user_id', 'admin_id'])]
class Conversation extends Model
{
    // Le client de la conversation
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // L'administrateur de la conversation
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    // Les messages de la conversation 
    public function messages() 
    {
     return $this->hasMany(Message::class, 'conversation_id'); 
    }
}