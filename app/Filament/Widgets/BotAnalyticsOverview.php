<?php

namespace App\Filament\Widgets;

use App\Services\BotAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class BotAnalyticsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $stats = app(BotAnalyticsService::class)->summaryByFilters($this->filters);

        return [
            Stat::make('Підписники бота', number_format($stats['bot_subscribers']))
                ->description('Користувачі, що писали боту')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),

            Stat::make('Підписані на канал', number_format($stats['channel_subscribers']))
                ->description('За даними getChatMember')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('success'),

            Stat::make('Підписки через бота (період)', number_format($stats['new_channel_joins_via_bot_30d']))
                ->color('primary'),

            Stat::make('Підписались (період)', number_format($stats['period_joins']))
                ->color('success'),

            Stat::make('Відписались (період)', number_format($stats['period_leaves']))
                ->color('danger'),

            Stat::make('Чистий приріст (період)', (string) $stats['period_net'])
                ->color($stats['period_net'] >= 0 ? 'success' : 'danger'),

            Stat::make('Конверсія', $stats['conversion_rate'] . '%')
                ->description('Канал через бота / підписники бота')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('Відписалися (загалом)', number_format($stats['channel_unsubscribed']))
                ->description('Були підписані, зараз ні')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('danger'),

            Stat::make('Сплеск дня', ($stats['peak_day_value'] >= 0 ? '+' : '') . $stats['peak_day_value'])
                ->description('Дата: ' . $stats['peak_day_date'])
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Просадка дня', (string) $stats['drop_day_value'])
                ->description('Дата: ' . $stats['drop_day_date'])
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Події доступні з', (string) $stats['events_available_from'])
                ->description('join/leave історія')
                ->color('gray'),

            Stat::make('Поточний діапазон', (string) $stats['range_label'])
                ->description('Фільтр аналітики')
                ->color('gray'),
        ];
    }
}
