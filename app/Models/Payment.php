<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    const PAYMENT_METHOD_CASH = 'cash';
    const PAYMENT_METHOD_BALANCE_DEDUCTION = 'balance_deduction';

    const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH => 'Готівка/Переказ',
        self::PAYMENT_METHOD_BALANCE_DEDUCTION => 'Списання з балансу',
    ];

    protected $fillable = [
        'debt_account_id',
        'order_id',
        'amount',
        'payment_method',
        'payment_type_id',
        'cash_register_id',
        'payment_date',
        'notes',
        'receipt_number',
        'receipts',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'receipts' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->receipt_number)) {
                $payment->receipt_number = 'PAY-' . date('Ymd') . '-' . str_pad(Payment::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });

        static::created(function ($payment) {
            // Оновлюємо заборгованість при створенні платежу
            if ($payment->debt_account_id) {
                // Для списання з балансу не оновлюємо DebtAccount автоматично
                if ($payment->payment_method !== self::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                    $payment->updateDebtAccount();
                }
            }
        });

        static::updated(function ($payment) {
            // Оновлюємо заборгованість при зміні платежу
            if ($payment->debt_account_id && $payment->isDirty(['amount', 'debt_account_id', 'order_id'])) {
                // Для списання з балансу не оновлюємо DebtAccount автоматично
                if ($payment->payment_method !== self::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                    $oldAmount = $payment->getOriginal('amount');
                    $payment->updateDebtAccount(false, $oldAmount);
                }
            }
        });

        static::deleted(function ($payment) {
            // Відновлюємо заборгованість при видаленні платежу
            // Для списання з балансу не оновлюємо DebtAccount автоматично
            if ($payment->payment_method !== self::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                $payment->updateDebtAccount(true);
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
        return number_format((float) $this->amount, 2) . ' грн';
    }

    public function getFormattedPaymentDateAttribute(): string
    {
        if (!$this->payment_date) {
            return '';
        }
        
        if (is_string($this->payment_date)) {
            return \Carbon\Carbon::parse($this->payment_date)->format('d.m.Y');
        }
        
        if ($this->payment_date instanceof \Carbon\Carbon) {
            return $this->payment_date->format('d.m.Y');
        }
        
        return '';
    }

    /**
     * Оновлює заборгованість при створенні/видаленні/зміні платежу
     */
    public function updateDebtAccount(bool $isDeletion = false, ?float $oldAmount = null): void
    {
        if (!$this->debt_account_id || !$this->debtAccount) {
            return;
        }

        if ($isDeletion) {
            // При видаленні - віднімаємо суму
            $amount = -$this->amount;
        } elseif ($oldAmount !== null) {
            // При редагуванні - розраховуємо різницю
            $amount = $this->amount - $oldAmount;
        } else {
            // При створенні - додаємо суму
            $amount = $this->amount;
        }

        // Оновлюємо заборгованість без тригеру observers
        $newPaidAmount = $this->debtAccount->paid_amount + $amount;
        $newRemainingDebt = $this->debtAccount->remaining_debt - $amount;
        $newBalance = $newPaidAmount - $this->debtAccount->total_debt;
        
        $status = $newRemainingDebt <= 0 ? DebtAccount::STATUS_CLOSED : DebtAccount::STATUS_ACTIVE;
        
        $this->debtAccount->updateQuietly([
            'paid_amount' => $newPaidAmount,
            'remaining_debt' => $newRemainingDebt,
            'balance' => $newBalance,
            'status' => $status,
        ]);

        // Оновлюємо замовлення якщо вказано
        if ($this->order_id) {
            $order = $this->order;
            if ($order) {
                $order->increment('paid_amount', $amount);
                $order->decrement('remaining_amount', $amount);

                // Оновлюємо статус замовлення
                $order->updateStatusBasedOnPayments();
            }
        } else {
            // Якщо платіж без замовлення - розподіляємо по замовленнях клієнта
            $this->distributePaymentToOrders($amount);
        }
    }

    /**
     * Розподіляє платіж по замовленнях клієнта
     */
    private function distributePaymentToOrders(float $amount): void
    {
        if (!$this->debtAccount) {
            return;
        }

        // Отримуємо замовлення з залишком до сплати, відсортовані за датою створення
        $orders = $this->debtAccount->orders()
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        $remainingAmount = $amount;

        foreach ($orders as $order) {
            if ($remainingAmount <= 0) {
                break;
            }

            $paymentAmount = min($remainingAmount, $order->remaining_amount);
            
            $order->increment('paid_amount', $paymentAmount);
            $order->decrement('remaining_amount', $paymentAmount);

            // Оновлюємо статус замовлення
            $order->updateStatusBasedOnPayments();

            $remainingAmount -= $paymentAmount;
        }
    }
}