<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;


class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Всього замовлень', Order::count())
                ->icon('heroicon-o-shopping-cart')
                ->color('info'),

            Stat::make('Нових замовлень', Order::where('status', 'new')->count())
                ->icon('heroicon-o-plus-circle')
                ->color('success'),

            Stat::make('Скасовано замовлень', Order::where('status', 'cancelled')->count())
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('Всього користувачів', Member::count())
                ->icon('heroicon-o-users')
                ->color('warning'),

            Stat::make('Сума продаж', number_format(Order::sum('total_amount'), 2, ',', ' ') . ' ₴')
                ->icon('heroicon-o-currency-dollar')
                ->color('primary'),

//            Stat::make('Сума продаж', number_format(Order::query()->whereBetween(Carbon::now()->startOfMonth(), Carbon::now())->sum('total_amount'), 2, ',', ' ') . ' ₴')
//                ->icon('heroicon-o-currency-dollar')
//                ->color('primary'),
        ];
    }
}
