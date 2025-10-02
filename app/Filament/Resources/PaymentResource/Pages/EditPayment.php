<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $data = $this->data;
        
        // Перевіряємо достатність балансу при зміні на списання з балансу
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

    protected function afterSave(): void
    {
        $record = $this->record;
        
        // Оновлюємо фінансові показники замовлення
        if ($record->order_id) {
            $order = $record->order;
            $order->updateOrderFinancials();
            
            // Обробляємо зміни в методі оплати
            $originalMethod = $record->getOriginal('payment_method');
            $newMethod = $record->payment_method;
            
            // Якщо змінили з готівки на баланс - списуємо з балансу
            if ($originalMethod !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION && 
                $newMethod === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                $record->debtAccount?->decrement('balance', $record->amount);
                PaymentResource::updateOrderStatusOnly($order);
            }
            // Якщо змінили з балансу на готівку - повертаємо на баланс
            elseif ($originalMethod === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION && 
                    $newMethod !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                $record->debtAccount?->increment('balance', $record->amount);
                // Для зовнішніх платежів оновлюємо все
                $order->updateDebtAccountTotals();
                $order->updateStatusBasedOnPayments();
            }
            // Якщо залишилися на балансі - оновлюємо тільки статус
            elseif ($newMethod === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                PaymentResource::updateOrderStatusOnly($order);
            }
            // Якщо залишилися на готівці - оновлюємо все
            else {
                $order->updateDebtAccountTotals();
                $order->updateStatusBasedOnPayments();
            }
        }
        
        Notification::make()
            ->success()
            ->title('Платіж оновлено')
            ->body('Платіж успішно оновлено')
            ->send();
    }
}
