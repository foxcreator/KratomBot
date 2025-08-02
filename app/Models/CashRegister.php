<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'details',
        'description',
        'payment_type_id',
        'balance',
    ];

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
