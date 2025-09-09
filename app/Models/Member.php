<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

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
        'full_name',
        'address',
        'city',
        'phone',
        'shipping_office',
    ];

    protected $casts = [
        'checkout_state' => 'array',
        'ui_state' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($member) {
            // Автоматично створюємо рахунок заборгованості для нового клієнта
            if (!$member->debtAccount) {
                \App\Models\DebtAccount::create([
                    'member_id' => $member->id,
                    'total_debt' => 0,
                    'paid_amount' => 0,
                    'remaining_debt' => 0,
                    'balance' => 0,
                    'status' => 'active',
                ]);
            }
        });
    }

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
