<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashWithdrawal extends Model
{
    protected $fillable = [
        'cash_register_id',
        'amount',
        'comment',
    ];

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }
}
