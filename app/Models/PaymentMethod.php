<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cash_register_id',
        'details',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Зв'язок з касою
     */
    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * Зв'язок з замовленнями
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Отримати реквізити для оплати (з каси або з власного поля)
     */
    public function getPaymentDetailsAttribute()
    {
        // Якщо є прив'язана каса, беремо реквізити з неї
        if ($this->cashRegister && $this->cashRegister->details) {
            return $this->cashRegister->details;
        }
        
        // Інакше беремо з власного поля
        return $this->details;
    }

    /**
     * Scope для активних варіантів оплати
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}