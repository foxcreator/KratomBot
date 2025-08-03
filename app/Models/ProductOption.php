<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'in_stock',
        'current_quantity',
        'current_purchase_price',
        'wholesale_price',
        'retail_price',
        'price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplyItems()
    {
        return $this->hasMany(SupplyItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
