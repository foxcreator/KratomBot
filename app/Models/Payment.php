<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'debt_account_id',
        'order_id',
        'amount',
        'payment_type_id',
        'cash_register_id',
        'payment_date',
        'notes',
        'receipt_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->receipt_number)) {
                $payment->receipt_number = 'PAY-' . date('Ymd') . '-' . str_pad(Payment::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function debtAccount(): BelongsTo
    {
        return $this->belongsTo(DebtAccount::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' грн';
    }

    public function getFormattedPaymentDateAttribute(): string
    {
        return $this->payment_date->format('d.m.Y');
    }
}