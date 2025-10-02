<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'debt_account_id',
        'order_number',
        'status',
        'total_amount',
        'final_amount',
        'paid_amount',
        'remaining_amount',
        'payment_status',
        'payment_type',
        'payment_method_id',
        'payment_receipt',
        'notes',
        'source',
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
        'final_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    const STATUS_NEW = 'new';
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_NEW => 'Нове',
        self::STATUS_PENDING_PAYMENT => 'Очікує оплати',
        self::STATUS_PARTIALLY_PAID => 'Частково оплачено',
        self::STATUS_PAID => 'Оплачено',
        self::STATUS_PROCESSING => 'Обробляється',
        self::STATUS_COMPLETED => 'Виконано',
        self::STATUS_CANCELLED => 'Скасовано',
    ];

    const PAYMENT_STATUS_UNPAID = 'unpaid';
    const PAYMENT_STATUS_PARTIAL_PAID = 'partial_paid';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_OVERPAID = 'overpaid';

    const PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_UNPAID => 'Не оплачено',
        self::PAYMENT_STATUS_PARTIAL_PAID => 'Частково оплачено',
        self::PAYMENT_STATUS_PAID => 'Оплачено',
        self::PAYMENT_STATUS_OVERPAID => 'Переплачено',
    ];

    const SOURCE_ADMIN = 'admin';
    const SOURCE_BOT = 'bot';

    const SOURCES = [
        self::SOURCE_ADMIN => 'Адмін-панель',
        self::SOURCE_BOT => 'Telegram бот',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-'. date('Ymd') . $order->id;
                $order->save();
            }
            
            // Автоматично створюємо DebtAccount для клієнта, якщо його немає
            if ($order->member_id && !$order->debt_account_id) {
                $debtAccount = \App\Models\DebtAccount::firstOrCreate(
                    ['member_id' => $order->member_id],
                    [
                        'total_debt' => 0,
                        'paid_amount' => 0,
                        'remaining_debt' => 0,
                        'balance' => 0,
                        'status' => \App\Models\DebtAccount::STATUS_ACTIVE,
                    ]
                );
                
                $order->update(['debt_account_id' => $debtAccount->id]);
                
                // Оновлюємо загальні суми в DebtAccount
                $order->updateDebtAccountTotals();
            }
        });

        static::updated(function (Order $order) {
            if (
                $order->isDirty('status') &&
                $order->status === Order::STATUS_PROCESSING &&
                $order->total_amount > 0
            ) {
                // Нараховуємо на касу через payment_method або напряму через cash_register_id
                $cashRegister = null;
                if ($order->payment_method_id && $order->paymentMethod && $order->paymentMethod->cashRegister) {
                    $cashRegister = $order->paymentMethod->cashRegister;
                } elseif ($order->cash_register_id) {
                    $cashRegister = $order->cashRegister;
                }
                
                if ($cashRegister) {
                    $cashRegister->increment('balance', (float) $order->total_amount);
                }
            }
            
            // Оновлюємо DebtAccount при зміні суми замовлення
            if ($order->isDirty(['total_amount', 'final_amount', 'paid_amount', 'remaining_amount'])) {
                $order->updateDebtAccountTotals();
            }
            
            // Оновлюємо статус замовлення на основі платежів
            if ($order->isDirty(['paid_amount', 'remaining_amount'])) {
                $order->updateStatusBasedOnPayments();
            }
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function debtAccount()
    {
        return $this->belongsTo(DebtAccount::class);
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

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalItemsAttribute()
    {
        return $this->orderItems->sum('quantity');
    }

    public function getFormattedTotalAttribute()
    {
        return number_format((float) $this->total_amount, 2) . ' грн';
    }

    public function getStatusNameAttribute()
    {
        return self::STATUSES[$this->status];
    }

    public function getPaymentStatusNameAttribute()
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? 'Невідомо';
    }

    public function getFormattedFinalAmountAttribute()
    {
        return number_format((float) ($this->final_amount ?? $this->total_amount), 2) . ' грн';
    }

    public function getFormattedPaidAmountAttribute()
    {
        return number_format((float) $this->paid_amount, 2) . ' грн';
    }

    public function getFormattedRemainingAmountAttribute()
    {
        return number_format((float) $this->remaining_amount, 2) . ' грн';
    }

    public function getSourceNameAttribute()
    {
        return self::SOURCES[$this->source] ?? 'Невідомо';
    }

    /**
     * Оновлює статус замовлення на основі платежів
     */
    public function updateStatusBasedOnPayments(): void
    {
        if ($this->remaining_amount <= 0) {
            $this->update([
                'status' => self::STATUS_PAID,
                'payment_status' => self::PAYMENT_STATUS_PAID
            ]);
        } elseif ($this->paid_amount > 0) {
            $this->update([
                'status' => self::STATUS_PARTIALLY_PAID,
                'payment_status' => self::PAYMENT_STATUS_PARTIAL_PAID
            ]);
        } else {
            $this->update([
                'status' => self::STATUS_PENDING_PAYMENT,
                'payment_status' => self::PAYMENT_STATUS_UNPAID
            ]);
        }
        
        // Оновлюємо загальний борг в DebtAccount
        $this->updateDebtAccountTotals();
    }
    
    /**
     * Оновлює загальні суми в DebtAccount
     */
    public function updateDebtAccountTotals(): void
    {
        if ($this->debtAccount) {
            // Використовуємо final_amount якщо є, інакше total_amount
            $totalDebt = $this->debtAccount->orders()->sum(DB::raw('COALESCE(final_amount, total_amount)'));
            $totalPaid = $this->debtAccount->payments()->sum('amount');
            
            $this->debtAccount->update([
                'total_debt' => $totalDebt,
                'paid_amount' => $totalPaid,
                'remaining_debt' => $totalDebt - $totalPaid,
            ]);
        }
    }
}
