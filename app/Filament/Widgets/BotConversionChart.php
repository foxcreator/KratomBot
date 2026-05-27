<?php

namespace App\Filament\Widgets;

use App\Services\BotAnalyticsService;
use Filament\Widgets\ChartWidget;

class BotConversionChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Воронка: бот → канал';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $analytics = app(BotAnalyticsService::class);
        $bot = $analytics->botSubscribersCount();
        $channel = $analytics->channelSubscribersCount();
        $viaBot = $analytics->channelJoinsViaBotCount();
        $notSubscribed = max(0, $bot - $channel);

        return [
            'datasets' => [
                [
                    'label' => 'Користувачі',
                    'data' => [$bot, $viaBot, $channel, $notSubscribed],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(148, 163, 184, 0.7)',
                    ],
                ],
            ],
            'labels' => [
                'У боті',
                'Канал через бота',
                'На каналі (всього)',
                'У боті без каналу',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
