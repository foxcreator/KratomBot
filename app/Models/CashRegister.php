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
    ];

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }
}
