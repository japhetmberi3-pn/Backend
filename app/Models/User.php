<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Shop;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
        ];
    }

    // Conversations où l'utilisateur est le client
    public function conversationsAsUser()
    {
        return $this->hasMany(Conversation::class, 'user_id');
    }

    // Conversations où l'utilisateur est l'administrateur
    public function conversationsAsAdmin()
    {
        return $this->hasMany(Conversation::class, 'admin_id');
    }

    // Messages envoyés par l'utilisateur
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // Vérifie si l'utilisateur est en ligne
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->greaterThanOrEqualTo(now()->subMinutes(5));
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

}