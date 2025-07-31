<?php

namespace App\Filament\Resources\CashWithdrawalResource\Pages;

use App\Filament\Resources\CashWithdrawalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCashWithdrawal extends EditRecord
{
    protected static string $resource = CashWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
//            Actions\DeleteAction::make(),
        ];
    }
}
