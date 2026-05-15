<?php

namespace App\Filament\Resources\BroadcastResource\Pages;

use App\Filament\Resources\BroadcastResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBroadcasts extends ListRecords
{
    protected static string $resource = BroadcastResource::class;

    public function getTitle(): string
    {
        return 'Розсилки';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Нова розсилка')
                ->icon('heroicon-o-paper-airplane'),
        ];
    }
}
