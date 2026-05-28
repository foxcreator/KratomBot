<?php

namespace App\Filament\Widgets;

use App\Services\BotAnalyticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class BotDailyBreakdownWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.bot-daily-breakdown-widget';

    protected function getViewData(): array
    {
        return app(BotAnalyticsService::class)->dailyBreakdownByFilters($this->filters);
    }
}
