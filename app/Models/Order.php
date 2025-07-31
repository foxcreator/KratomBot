<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'order_number',
        'status',
        'total_amount',
        'notes',
        'source',
        'payment_type',
        'payment_receipt',
        'shipping_phone',
        'shipping_city',
        'shipping_carrier',
        'shipping_office',
        'shipping_name',
        'discount_percent',
        'discount_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    const STATUS_NEW = 'new';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_NEW => 'Нове',
        self::STATUS_COMPLETED => 'Виконано',
        self::STATUS_PROCESSING => 'В обробці',
        self::STATUS_CANCELLED => 'Скасовано',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-'. date('Ymd') . $order->id;
                $order->save();
            }
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
                    ->withPivot('quantity', 'price')
                    ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTotalItemsAttribute()
    {
        return $this->orderItems->sum('quantity');
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2) . ' грн';
    }

    public function getStatusNameAttribute()
    {
        return self::STATUSES[$this->status];
    }
}
