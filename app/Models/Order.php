<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',

        // Informations du destinataire
        'recipient_name',
        'phone',

        // Adresse de livraison
        'city',
        'neighborhood',
        'address',
        'address_details',

        // Position GPS
        'latitude',
        'longitude',

        // Informations complémentaires
        'delivery_note',

        // Livraison
        'delivery_date',
        'delivery_time',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'delivery_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}