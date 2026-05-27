<?php

namespace App\Filament\Widgets;

use App\Services\BotAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BotAnalyticsOverview extends BaseWidget
{
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
        $stats = app(BotAnalyticsService::class)->summary();

        return [
            Stat::make('Підписники бота', number_format($stats['bot_subscribers']))
                ->description('Користувачі, що писали боту')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),

            Stat::make('Підписані на канал', number_format($stats['channel_subscribers']))
                ->description('За даними getChatMember')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('success'),

            Stat::make('Через бота', number_format($stats['channel_joins_via_bot']))
                ->description('Підтверджені підписки з бота')
                ->descriptionIcon('heroicon-m-link')
                ->color('primary'),

            Stat::make('Конверсія', $stats['conversion_rate'] . '%')
                ->description('Канал через бота / підписники бота')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('Нові в боті (30 дн.)', number_format($stats['new_bot_subscribers_30d']))
                ->color('info'),

            Stat::make('Підписки через бота (30 дн.)', number_format($stats['new_channel_joins_via_bot_30d']))
                ->color('primary'),

            Stat::make('Активні (7 дн.)', number_format($stats['active_bot_users_7d']))
                ->description('Писали боту за тиждень')
                ->color('success'),

            Stat::make('Клікнули, не підписались', number_format($stats['channel_link_clicks_unconverted']))
                ->description('Потенційні підписники')
                ->color('gray'),
        ];
    }
}
