<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BotAnalyticsChart;
use App\Filament\Widgets\BotDailyBreakdownWidget;
use App\Filament\Widgets\BotAnalyticsOverview;
use App\Filament\Widgets\BotConversionChart;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class BotAnalyticsDashboard extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Аналітика бота';

    protected static ?string $title = 'Аналітика Telegram-бота';

    protected static ?string $slug = 'bot-analytics';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament-panels::pages.dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            BotAnalyticsOverview::class,
            BotAnalyticsChart::class,
            BotConversionChart::class,
            BotDailyBreakdownWidget::class,
        ];
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return 2;
    }

    public function mount(): void
    {
        $this->filters ??= [
            'period' => '30',
            'start_date' => null,
            'end_date' => null,
        ];
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('period')
                ->label('Період')
                ->options([
                    '7' => 'Останні 7 днів',
                    '14' => 'Останні 14 днів',
                    '30' => 'Останні 30 днів',
                    '60' => 'Останні 60 днів',
                    '90' => 'Останні 90 днів',
                    'custom' => 'Кастомний діапазон',
                ])
                ->default('30')
                ->live(),
            DatePicker::make('start_date')
                ->label('Початок')
                ->visible(fn ($get) => $get('period') === 'custom'),
            DatePicker::make('end_date')
                ->label('Кінець')
                ->visible(fn ($get) => $get('period') === 'custom'),
        ]);
    }
}
