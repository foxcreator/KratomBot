<?php

namespace App\Filament\Resources\CashWithdrawalResource\Pages;

use App\Filament\Resources\CashWithdrawalResource;
use App\Models\CashRegister;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCashWithdrawal extends CreateRecord
{
    protected static string $resource = CashWithdrawalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $cashRegister = CashRegister::findOrFail($data['cash_register_id']);

        if ($cashRegister->balance < $data['amount']) {
            Notification::make()
                ->title('Недостатньо коштів у касі')
                ->body('Сума виносу перевищує баланс: ' . number_format($cashRegister->balance, 2, ',', ' ') . ' ₴')
                ->danger()
                ->persistent()
                ->send();

            // ⛔️ Скасувати збереження
            $this->halt(); // метод Livewire\Component, що зупиняє виконання

//            return $data; // формально треба щось повернути, але воно не буде оброблене
        }

        $cashRegister->decrement('balance', $data['amount']);

        return $data;
    }
}
