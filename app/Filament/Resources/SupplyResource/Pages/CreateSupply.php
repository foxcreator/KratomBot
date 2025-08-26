<?php

namespace App\Filament\Resources\SupplyResource\Pages;

use App\Filament\Resources\SupplyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSupply extends CreateRecord
{
    protected static string $resource = SupplyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        foreach ($record->supplyItems as $item) {
            // оновлюємо залишки
            $productOption = $item->productOption;

            $productOption->increment('current_quantity', $item->quantity);
            $productOption->update([
                'in_stock' => true,
            ]);

            // оновлюємо залишок у supply_item
            $item->remaining_quantity = $item->quantity;
            $item->save();
        }
    }
}
