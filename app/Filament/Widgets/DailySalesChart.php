<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DailySalesChart extends ChartWidget
{
    protected static ?string $heading = 'Продажі за останні 30 днів';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days = 30;

        // Беремо суми замовлень по днях
        $sales = DB::table('orders')
            ->selectRaw('DATE(created_at) as day, SUM(total_amount) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Заповнюємо пропущені дні нулями
        $dateLabels = collect();
        $dateMap = collect();

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dateLabels->push(Carbon::parse($date)->format('d.m'));
            $dateMap[$date] = 0;
        }

        foreach ($sales as $row) {
            $dateMap[$row->day] = $row->total;
        }

        $data = $dateMap->values()->toArray();

        // Рахуємо динаміку зміни
        $changes = [];
        foreach ($data as $i => $value) {
            if ($i === 0) {
                $changes[] = 0;
                continue;
            }
            $prev = $data[$i - 1];
            $change = $prev > 0 ? round((($value - $prev) / $prev) * 100, 1) : 0;
            $changes[] = $change;
        }

        return [
            'datasets' => [
                [
                    'label' => '₴ Продажі',
                    'data' => $data,
                    'backgroundColor' => collect($changes)->map(function ($change) {
                        if ($change > 0) return 'rgba(37,144,0,0.6)';
                        if ($change < 0) return 'rgba(144,6,37,0.6)';
                        return 'rgba(52,54,94,0.79)';
                    }),
                ]
            ],
            'labels' => $dateLabels->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
