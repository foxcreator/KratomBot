<?php

namespace App\Filament\Resources\DebtAccountResource\Pages;

use App\Filament\Resources\DebtAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDebtAccounts extends ListRecords
{
    protected static string $resource = DebtAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
