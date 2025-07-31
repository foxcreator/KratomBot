<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesChart extends ChartWidget
{
    protected static ?string $heading = 'Динаміка продажів по місяцях';
    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $sales = DB::table('orders')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = $sales->pluck('month')->toArray();
        $data = $sales->pluck('total')->toArray();

        // Рахуємо зміну від попереднього місяця в %
        $changes = [];
        foreach ($data as $index => $value) {
            if ($index === 0) {
                $changes[] = 0;
                continue;
            }
            $prev = $data[$index - 1];
            $change = $prev > 0 ? round((($value - $prev) / $prev) * 100, 1) : 0;
            $changes[] = $change;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Сума продажів ₴',
                    'data' => $data,
                    'backgroundColor' => collect($changes)->map(function ($change) {
                        if ($change > 0) return 'rgba(37,144,0,0.6)';
                        if ($change < 0) return 'rgba(144,6,37,0.6)';
                        return 'rgba(52,54,94,0.79)';
                    }),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
