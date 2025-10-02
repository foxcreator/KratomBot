<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DebtAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'total_debt',
        'paid_amount',
        'remaining_debt',
        'balance',
        'status',
        'last_payment_date',
        'notes',
    ];

    protected $casts = [
        'total_debt' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_debt' => 'decimal:2',
        'balance' => 'decimal:2',
        'last_payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
        return number_format((float) $this->total_debt, 2) . ' грн';
    }

    public function getFormattedPaidAmountAttribute(): string
    {
        return number_format((float) $this->paid_amount, 2) . ' грн';
    }

    public function getFormattedRemainingDebtAttribute(): string
    {
        return number_format((float) $this->remaining_debt, 2) . ' грн';
    }

    public function addPayment(float $amount, int $paymentTypeId, int $cashRegisterId, ?int $orderId = null, ?string $notes = null, string $paymentMethod = Payment::PAYMENT_METHOD_CASH): Payment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_type_id' => $paymentTypeId,
            'cash_register_id' => $cashRegisterId,
            'order_id' => $orderId,
            'payment_date' => now(),
            'notes' => $notes,
        ]);

        // Оновлюємо дату останнього платежу
        $this->update(['last_payment_date' => now()]);
        
        // Перераховуємо всі суми автоматично
        $this->recalculateTotals();

        // Якщо платіж з балансу - списуємо з балансу
        if ($paymentMethod === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
            $this->decrement('balance', $amount);
        }

        // Оновлення замовлення відбувається автоматично через Payment model events

        return $payment;
    }

    public function addToBalance(float $amount, ?string $notes = null): void
    {
        $this->increment('balance', $amount);
        
        // Створюємо запис про поповнення балансу
        $this->payments()->create([
            'amount' => $amount,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
            'payment_type_id' => 1, // Готівка
            'cash_register_id' => 1, // Основна каса
            'payment_date' => now(),
            'notes' => $notes ?? 'Поповнення балансу',
        ]);
    }
    
    /**
     * Перераховує всі суми автоматично
     */
    public function recalculateTotals(): void
    {
        $totalDebt = $this->orders()->sum('final_amount');
        $totalPaid = $this->payments()->sum('amount');
        $remainingDebt = max(0, $totalDebt - $totalPaid);
        $balance = $totalPaid - $totalDebt;
        
        $this->update([
            'total_debt' => $totalDebt,
            'paid_amount' => $totalPaid,
            'remaining_debt' => $remainingDebt,
            'balance' => $balance,
        ]);
        
        // Оновлюємо статус
        if ($remainingDebt <= 0) {
            $this->update(['status' => self::STATUS_CLOSED]);
        } else {
            $this->update(['status' => self::STATUS_ACTIVE]);
        }
    }
}