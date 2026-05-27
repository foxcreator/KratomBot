<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Member extends Model
{
    use HasFactory;

    public const CHANNEL_JOIN_SOURCE_BOT = 'bot';
    public const CHANNEL_JOIN_SOURCE_ORGANIC = 'organic';
    public const CHANNEL_JOIN_SOURCE_UNKNOWN = 'unknown';

    protected $fillable = [
        'phone',
        'telegram_id',
        'is_subscribed',
        'bot_started_at',
        'last_interaction_at',
        'channel_joined_at',
        'channel_join_source',
        'channel_link_clicked_at',
        'username',
        'current_brand_id',
        'checkout_state',
        'full_name',
        'address',
        'city',
        'phone',
        'shipping_office',
    ];

    protected $casts = [
        'checkout_state' => 'array',
        'ui_state' => 'array',
        'is_subscribed' => 'boolean',
        'bot_started_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'channel_joined_at' => 'datetime',
        'channel_link_clicked_at' => 'datetime',
    ];

    public function promocode()
    {
        return $this->hasOne(Promocode::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function debtAccount()
    {
        return $this->hasOne(DebtAccount::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function broadcastRecipients()
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function getCartTotalAttribute()
    {
        return $this->cartItems->sum(function ($item) {
            return $item->quantity * (float) $item->product->price;
        });
    }

    public function getCartItemsCountAttribute()
    {
        return $this->cartItems->sum('quantity');
    }

    public function getFullNameAttribute(): string
    {
        return Arr::get($this->attributes, 'full_name')
            ?? Arr::get($this->attributes, 'username')
            ?? 'Без імені';
    }

    public function getTotalOrdersAmountAttribute(): float
    {
        return $this->orders()->sum('total_amount');
    }

    public function getTotalOrdersCountAttribute(): int
    {
        return $this->orders()->count();
    }

    public function getFormattedTotalOrdersAmountAttribute(): string
    {
        return number_format($this->total_orders_amount, 2) . ' ₴';
    }
}
