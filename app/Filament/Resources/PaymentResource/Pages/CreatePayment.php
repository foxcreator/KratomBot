<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Якщо списання з балансу - встановлюємо значення за замовчуванням для обов'язкових полів
        if (isset($data['payment_method']) && $data['payment_method'] === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
            // Встановлюємо перший доступний тип оплати як замовчування
            if (empty($data['payment_type_id'])) {
                $firstPaymentType = \App\Models\PaymentType::first();
                $data['payment_type_id'] = $firstPaymentType?->id ?? 1;
            }
            
            // Встановлюємо першу доступну касу як замовчування
            if (empty($data['cash_register_id'])) {
                $firstCashRegister = \App\Models\CashRegister::first();
                $data['cash_register_id'] = $firstCashRegister?->id ?? 1;
            }
        }
        
        return $data;
    }

    protected function beforeCreate(): void
    {
        $data = $this->data;
        
        // Перевіряємо достатність балансу перед створенням платежу
        if (isset($data['payment_method']) && 
            $data['payment_method'] === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
            
            if (!PaymentResource::hasSufficientBalance($data['amount'], $data['debt_account_id'])) {
                $debtAccount = \App\Models\DebtAccount::find($data['debt_account_id']);
                $balance = $debtAccount?->balance ?? 0;
                
                Notification::make()
                    ->danger()
                    ->title('Недостатньо коштів на балансі')
                    ->body("На балансі доступно: " . number_format($balance, 2) . " ₴")
                    ->send();
                
                $this->halt();
            }
        }
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Оновлюємо фінансові показники замовлення
        if ($record->order_id) {
            $order = $record->order;
            $order->updateOrderFinancials();
            
            // Якщо платіж з балансу - списуємо з балансу
            if ($record->payment_method === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                $record->debtAccount?->decrement('balance', $record->amount);
                // Для списання з балансу оновлюємо тільки статус замовлення без DebtAccount
                PaymentResource::updateOrderStatusOnly($order);
            } else {
                // Для зовнішніх платежів оновлюємо все
                $order->updateDebtAccountTotals();
                $order->updateStatusBasedOnPayments();
            }
        }
        
        $methodText = $record->payment_method === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION 
            ? 'з балансу клієнта' 
            : 'до системи';
            
        Notification::make()
            ->success()
            ->title('Платіж створено')
            ->body("Платіж успішно створено {$methodText}")
            ->send();
    }
}
