<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebtAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'total_debt',
        'paid_amount',
        'remaining_debt',
        'status',
        'last_payment_date',
        'notes',
    ];

    protected $casts = [
        'total_debt' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_debt' => 'decimal:2',
        'last_payment_date' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';
    const STATUS_OVERDUE = 'overdue';

    const STATUSES = [
        self::STATUS_ACTIVE => 'Активний',
        self::STATUS_CLOSED => 'Закритий',
        self::STATUS_OVERDUE => 'Прострочений',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($debtAccount) {
            // Автоматично розраховуємо залишок боргу
            $debtAccount->remaining_debt = $debtAccount->total_debt - $debtAccount->paid_amount;
            
            // Встановлюємо статус на основі залишку
            if ($debtAccount->remaining_debt <= 0) {
                $debtAccount->status = self::STATUS_CLOSED;
            } elseif ($debtAccount->remaining_debt > 0) {
                $debtAccount->status = self::STATUS_ACTIVE;
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Невідомо';
    }

    public function getFormattedTotalDebtAttribute(): string
    {
        return number_format($this->total_debt, 2) . ' грн';
    }

    public function getFormattedPaidAmountAttribute(): string
    {
        return number_format($this->paid_amount, 2) . ' грн';
    }

    public function getFormattedRemainingDebtAttribute(): string
    {
        return number_format($this->remaining_debt, 2) . ' грн';
    }

    public function addPayment(float $amount, int $paymentTypeId, int $cashRegisterId, ?int $orderId = null, ?string $notes = null): Payment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'payment_type_id' => $paymentTypeId,
            'cash_register_id' => $cashRegisterId,
            'order_id' => $orderId,
            'payment_date' => now(),
            'notes' => $notes,
        ]);

        // Оновлюємо суми
        $this->increment('paid_amount', $amount);
        $this->update(['last_payment_date' => now()]);

        // Оновлюємо замовлення якщо вказано
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $order->increment('paid_amount', $amount);
                $order->decrement('remaining_amount', $amount);
                
                // Оновлюємо статус оплати замовлення
                if ($order->remaining_amount <= 0) {
                    $order->update(['payment_status' => 'paid']);
                } else {
                    $order->update(['payment_status' => 'partial_paid']);
                }
            }
        }

        return $payment;
    }
}