<?php

namespace App\Services;

use App\Models\ChannelSubscriptionEvent;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BotAnalyticsService
{
    private const ANALYTICS_TIMEZONE = 'Europe/Kyiv';

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
        return ChannelSubscriptionEvent::query()
            ->where('event_type', ChannelSubscriptionEvent::TYPE_JOIN)
            ->where('source', Member::CHANNEL_JOIN_SOURCE_BOT)
            ->where('occurred_at', '>=', now()->subDays($days))
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
        return ChannelSubscriptionEvent::query()
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as count')
            ->where('event_type', ChannelSubscriptionEvent::TYPE_JOIN)
            ->where('source', Member::CHANNEL_JOIN_SOURCE_BOT)
            ->where('occurred_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * @return array{labels: array<int, string>, joins: array<int, int>, leaves: array<int, int>, netGrowth: array<int, int>}
     */
    public function chartData(int $days = 30): array
    {
        $series = $this->dailySubscriptionSeries($days);

        return [
            'labels' => $series['labels'],
            'joins' => $series['joins'],
            'leaves' => $series['leaves'],
            'netGrowth' => $series['netGrowth'],
        ];
    }

    /**
     * @param array<string, mixed>|null $filters
     * @return array{labels: array<int, string>, joins: array<int, int>, leaves: array<int, int>, netGrowth: array<int, int>}
     */
    public function chartDataByFilters(?array $filters): array
    {
        $range = $this->resolveRange($filters);
        $series = $this->dailySeriesBetween($range['from'], $range['to']);

        return [
            'labels' => $series['labels'],
            'joins' => $series['joins'],
            'leaves' => $series['leaves'],
            'netGrowth' => $series['netGrowth'],
        ];
    }

    /**
     * @return array{labels: array<int, string>, joins: array<int, int>, leaves: array<int, int>, netGrowth: array<int, int>, peakDay: array{date: string, value: int}, dropDay: array{date: string, value: int}, firstEventDate: ?string}
     */
    public function dailySubscriptionSeries(int $days = 30): array
    {
        $from = now(self::ANALYTICS_TIMEZONE)->subDays($days - 1)->startOfDay();
        $to = now(self::ANALYTICS_TIMEZONE)->endOfDay();

        return $this->dailySeriesBetween($from, $to);
    }

    /**
     * @param Carbon $from Local timezone datetime
     * @param Carbon $to Local timezone datetime
     * @return array{labels: array<int, string>, joins: array<int, int>, leaves: array<int, int>, netGrowth: array<int, int>, peakDay: array{date: string, value: int}, dropDay: array{date: string, value: int}, firstEventDate: ?string}
     */
    private function dailySeriesBetween(Carbon $from, Carbon $to): array
    {
        $labels = [];
        $joins = [];
        $leaves = [];
        $netGrowth = [];

        $fromUtc = $from->copy()->setTimezone('UTC');
        $toUtc = $to->copy()->setTimezone('UTC');
        $events = ChannelSubscriptionEvent::query()
            ->whereBetween('occurred_at', [$fromUtc, $toUtc])
            ->orderBy('occurred_at')
            ->get(['event_type', 'occurred_at']);

        $daily = [];
        foreach ($events as $event) {
            $day = Carbon::parse($event->occurred_at)->setTimezone(self::ANALYTICS_TIMEZONE)->format('Y-m-d');
            if (!isset($daily[$day])) {
                $daily[$day] = ['join' => 0, 'leave' => 0];
            }
            if ($event->event_type === ChannelSubscriptionEvent::TYPE_JOIN) {
                $daily[$day]['join']++;
            } elseif ($event->event_type === ChannelSubscriptionEvent::TYPE_LEAVE) {
                $daily[$day]['leave']++;
            }
        }

        $peakDay = ['date' => '—', 'value' => 0];
        $dropDay = ['date' => '—', 'value' => 0];

        $cursor = $from->copy()->startOfDay();
        $endDate = $to->copy()->startOfDay();

        while ($cursor <= $endDate) {
            $dateKey = $cursor->format('Y-m-d');
            $joinCount = (int) ($daily[$dateKey]['join'] ?? 0);
            $leaveCount = (int) ($daily[$dateKey]['leave'] ?? 0);
            $delta = $joinCount - $leaveCount;

            $labels[] = $cursor->format('d.m');
            $joins[] = $joinCount;
            $leaves[] = $leaveCount;
            $netGrowth[] = $delta;

            if ($delta > $peakDay['value']) {
                $peakDay = ['date' => $cursor->format('d.m'), 'value' => $delta];
            }
            if ($delta < $dropDay['value']) {
                $dropDay = ['date' => $cursor->format('d.m'), 'value' => $delta];
            }

            $cursor->addDay();
        }

        $firstEventDate = ChannelSubscriptionEvent::query()->min('occurred_at');

        return [
            'labels' => $labels,
            'joins' => $joins,
            'leaves' => $leaves,
            'netGrowth' => $netGrowth,
            'peakDay' => $peakDay,
            'dropDay' => $dropDay,
            'firstEventDate' => $firstEventDate
                ? Carbon::parse($firstEventDate)->setTimezone(self::ANALYTICS_TIMEZONE)->format('d.m.Y H:i')
                : null,
        ];
    }

    /**
     * @param array<string, mixed>|null $filters
     * @return array{rows: array<int, array<string, mixed>>, from: string, to: string}
     */
    public function dailyBreakdownByFilters(?array $filters): array
    {
        $range = $this->resolveRange($filters);
        $series = $this->dailySeriesBetween($range['from'], $range['to']);

        $rows = [];
        foreach ($series['labels'] as $index => $label) {
            $rows[] = [
                'date' => $label,
                'joins' => $series['joins'][$index] ?? 0,
                'leaves' => $series['leaves'][$index] ?? 0,
                'net' => $series['netGrowth'][$index] ?? 0,
            ];
        }

        return [
            'rows' => array_reverse($rows),
            'from' => $range['from']->format('d.m.Y'),
            'to' => $range['to']->format('d.m.Y'),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $daily = $this->dailySubscriptionSeries(30);

        return [
            'bot_subscribers' => $this->botSubscribersCount(),
            'channel_subscribers' => $this->channelSubscribersCount(),
            'channel_joins_via_bot' => $this->channelJoinsViaBotCount(),
            'channel_unsubscribed' => $this->channelUnsubscribedCount(),
            'conversion_rate' => $this->conversionRate(),
            'new_bot_subscribers_30d' => $this->newBotSubscribersCount(30),
            'new_channel_joins_via_bot_30d' => $this->newChannelJoinsViaBotCount(30),
            'peak_day_date' => $daily['peakDay']['date'],
            'peak_day_value' => $daily['peakDay']['value'],
            'drop_day_date' => $daily['dropDay']['date'],
            'drop_day_value' => $daily['dropDay']['value'],
            'events_available_from' => $daily['firstEventDate'] ?? 'ще немає подій',
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

    /**
     * @param array<string, mixed>|null $filters
     * @return array<string, int|float|string>
     */
    public function summaryByFilters(?array $filters): array
    {
        $range = $this->resolveRange($filters);
        $daily = $this->dailySeriesBetween($range['from'], $range['to']);
        $startUtc = $range['from']->copy()->setTimezone('UTC');
        $endUtc = $range['to']->copy()->setTimezone('UTC');

        $joinsInRange = ChannelSubscriptionEvent::query()
            ->where('event_type', ChannelSubscriptionEvent::TYPE_JOIN)
            ->whereBetween('occurred_at', [$startUtc, $endUtc])
            ->count();

        $leavesInRange = ChannelSubscriptionEvent::query()
            ->where('event_type', ChannelSubscriptionEvent::TYPE_LEAVE)
            ->whereBetween('occurred_at', [$startUtc, $endUtc])
            ->count();

        $summary = $this->summary();
        $summary['new_channel_joins_via_bot_30d'] = ChannelSubscriptionEvent::query()
            ->where('event_type', ChannelSubscriptionEvent::TYPE_JOIN)
            ->where('source', Member::CHANNEL_JOIN_SOURCE_BOT)
            ->whereBetween('occurred_at', [$startUtc, $endUtc])
            ->count();
        $summary['period_joins'] = $joinsInRange;
        $summary['period_leaves'] = $leavesInRange;
        $summary['period_net'] = $joinsInRange - $leavesInRange;
        $summary['peak_day_date'] = $daily['peakDay']['date'];
        $summary['peak_day_value'] = $daily['peakDay']['value'];
        $summary['drop_day_date'] = $daily['dropDay']['date'];
        $summary['drop_day_value'] = $daily['dropDay']['value'];
        $summary['range_label'] = $range['from']->format('d.m.Y') . ' - ' . $range['to']->format('d.m.Y');

        return $summary;
    }

    /**
     * @param array<string, mixed>|null $filters
     * @return array{from: Carbon, to: Carbon}
     */
    private function resolveRange(?array $filters): array
    {
        $period = (string) ($filters['period'] ?? '30');
        $today = now(self::ANALYTICS_TIMEZONE);

        if ($period === 'custom') {
            $start = !empty($filters['start_date'])
                ? Carbon::parse((string) $filters['start_date'], self::ANALYTICS_TIMEZONE)->startOfDay()
                : $today->copy()->subDays(29)->startOfDay();
            $end = !empty($filters['end_date'])
                ? Carbon::parse((string) $filters['end_date'], self::ANALYTICS_TIMEZONE)->endOfDay()
                : $today->copy()->endOfDay();

            if ($start->gt($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            return ['from' => $start, 'to' => $end];
        }

        $days = max(1, (int) $period);
        return [
            'from' => $today->copy()->subDays($days - 1)->startOfDay(),
            'to' => $today->copy()->endOfDay(),
        ];
    }
}
