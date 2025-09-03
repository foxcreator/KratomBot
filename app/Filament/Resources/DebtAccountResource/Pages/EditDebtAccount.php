<?php

namespace App\Filament\Resources\DebtAccountResource\Pages;

use App\Filament\Resources\DebtAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDebtAccount extends EditRecord
{
    protected static string $resource = DebtAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
