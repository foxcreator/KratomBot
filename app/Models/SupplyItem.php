<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplyItem extends Model
{
    protected $fillable = [
        'supply_id',
        'product_option_id',
        'quantity',
        'purchase_price',
        'remaining_quantity',
    ];

    public function supply(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function productOption(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected static function booted()
    {
        static::creating(function ($item) {
            $item->remaining_quantity = $item->quantity;
        });
    }
}
