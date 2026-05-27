<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BotAnalyticsChart;
use App\Filament\Widgets\BotAnalyticsOverview;
use App\Filament\Widgets\BotConversionChart;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class BotAnalyticsDashboard extends Page
{
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
}
