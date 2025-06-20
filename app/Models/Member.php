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
    ];

    public function promocode()
    {
        return $this->hasOne(Promocode::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
