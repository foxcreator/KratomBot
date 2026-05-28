<?php

namespace App\Services;

use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Collection;
class BotAnalyticsService
{
    public function botSubscribersCount(): int
    {
        return Member::query()
            ->whereNotNull('telegram_id')
            ->where('telegram_id', '!=', '')
            ->count();
    }

    public function channelSubscribersCount(): int
    {
        return Member::query()
            ->whereNotNull('telegram_id')
            ->where('is_subscribed', true)
            ->count();
    }

    public function channelJoinsViaBotCount(): int
    {
        return Member::query()
            ->where('channel_join_source', Member::CHANNEL_JOIN_SOURCE_BOT)
            ->count();
    }

    public function channelUnsubscribedCount(): int
    {
        // Рахуємо тільки тих, хто підтверджено підписувався (channel_joined_at є)
        // І підтверджено відписався (is_subscribed = false).
        // NULL = "ніколи не перевірявся" — це НЕ відписка, не рахуємо.
        return Member::query()
            ->whereNotNull('telegram_id')
            ->whereNotNull('channel_joined_at')
            ->where('is_subscribed', false)
            ->count();
    }

    public function conversionRate(): float
    {
        $botUsers = $this->botSubscribersCount();
        if ($botUsers === 0) {
            return 0.0;
        }

        return round(($this->channelJoinsViaBotCount() / $botUsers) * 100, 1);
    }

    public function newBotSubscribersCount(int $days = 30): int
    {
        return Member::query()
            ->whereNotNull('telegram_id')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    public function newChannelJoinsViaBotCount(int $days = 30): int
    {
        return Member::query()
            ->where('channel_join_source', Member::CHANNEL_JOIN_SOURCE_BOT)
            ->where('channel_joined_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * @return Collection<int, object{date: string, count: int}>
     */
    public function botSubscribersPerDay(int $days = 30): Collection
    {
        return Member::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereNotNull('telegram_id')
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * @return Collection<int, object{date: string, count: int}>
     */
    public function channelJoinsViaBotPerDay(int $days = 30): Collection
    {
        return Member::query()
            ->selectRaw('DATE(channel_joined_at) as date, COUNT(*) as count')
            ->where('channel_join_source', Member::CHANNEL_JOIN_SOURCE_BOT)
            ->whereNotNull('channel_joined_at')
            ->where('channel_joined_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * @return array{labels: array<int, string>, bot: array<int, int>, channel: array<int, int>}
     */
    public function chartData(int $days = 30): array
    {
        $labels = [];
        $botData = [];
        $channelData = [];

        $botByDay = $this->botSubscribersPerDay($days)->keyBy('date');
        $channelByDay = $this->channelJoinsViaBotPerDay($days)->keyBy('date');

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('d.m');
            $botData[] = (int) ($botByDay[$date]->count ?? 0);
            $channelData[] = (int) ($channelByDay[$date]->count ?? 0);
        }

        return [
            'labels' => $labels,
            'bot' => $botData,
            'channel' => $channelData,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        return [
            'bot_subscribers' => $this->botSubscribersCount(),
            'channel_subscribers' => $this->channelSubscribersCount(),
            'channel_joins_via_bot' => $this->channelJoinsViaBotCount(),
            'channel_unsubscribed' => $this->channelUnsubscribedCount(),
            'conversion_rate' => $this->conversionRate(),
            'new_bot_subscribers_30d' => $this->newBotSubscribersCount(30),
            'new_channel_joins_via_bot_30d' => $this->newChannelJoinsViaBotCount(30),
            'active_bot_users_7d' => Member::query()
                ->whereNotNull('telegram_id')
                ->where('last_interaction_at', '>=', now()->subDays(7))
                ->count(),
            'channel_link_clicks_unconverted' => Member::query()
                ->whereNotNull('channel_link_clicked_at')
                ->where(function ($q) {
                    $q->whereNull('channel_join_source')
                        ->orWhere('channel_join_source', '!=', Member::CHANNEL_JOIN_SOURCE_BOT);
                })
                ->where('is_subscribed', '!=', true)
                ->count(),
        ];
    }
}
