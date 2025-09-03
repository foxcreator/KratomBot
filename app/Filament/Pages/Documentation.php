<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class Documentation extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Документація';
    protected static ?string $title = 'Документація системи';
    protected static ?string $navigationGroup = 'Налаштування';
    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.documentation';

    public function getActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Оновити')
                ->icon('heroicon-o-arrow-path')
                ->size(ActionSize::Small)
                ->action('refresh'),
        ];
    }

    public function refresh(): void
    {
        $this->dispatch('$refresh');
    }
}