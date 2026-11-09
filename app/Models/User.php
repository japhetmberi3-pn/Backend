<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Shop;
use App\Models\Order;
use App\Models\Cart;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'google_id',
    'profile_photo',
])]
#[Hidden([
    'password',
    'remember_token',
    'google_id',
])]
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

    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    // Messages envoyés par l'utilisateur
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Statut en ligne
    |--------------------------------------------------------------------------
    */

    // Vérifie si l'utilisateur est en ligne
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->greaterThanOrEqualTo(
                now()->subMinutes(5)
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Panier
    |--------------------------------------------------------------------------
    */

    // Panier de l'utilisateur
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Boutique
    |--------------------------------------------------------------------------
    */

    // Boutique du vendeur
    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Commandes
    |--------------------------------------------------------------------------
    */

    // Commandes de l'utilisateur
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}