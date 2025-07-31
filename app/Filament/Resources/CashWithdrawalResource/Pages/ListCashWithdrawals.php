<?php

namespace App\Filament\Resources\CashWithdrawalResource\Pages;

use App\Filament\Resources\CashWithdrawalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashWithdrawals extends ListRecords
{
    protected static string $resource = CashWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
