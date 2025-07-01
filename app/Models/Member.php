<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'telegram_id',
        'is_subscribed',
        'username',
        'current_brand_id',
        'checkout_state',
    ];

    protected $casts = [
        'checkout_state' => 'array',
    ];

    public function promocode()
    {
        return $this->hasOne(Promocode::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
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
}
