<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\Order;
use App\Models\DebtAccount;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;


class StatsOverview extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        // Статистика по замовленнях
        $totalOrders = Order::count();
        $newOrders = Order::where('status', 'new')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();
        $unpaidOrders = Order::whereIn('payment_status', ['unpaid', 'partial_paid'])->count();
        
        // Статистика по боргах
        $totalDebt = DebtAccount::sum('total_debt');
        $totalPaid = DebtAccount::sum('paid_amount');
        $totalRemainingDebt = DebtAccount::sum('remaining_debt');
        $totalBalance = DebtAccount::sum('balance');
        
        // Статистика по клієнтах
        $totalMembers = Member::count();
        $membersWithDebt = Member::whereHas('debtAccount', function($query) {
            $query->where('remaining_debt', '>', 0);
        })->count();
        
        // Загальна сума замовлень
        $totalOrderAmount = Order::sum('total_amount');

        return [
            Stat::make('Всього замовлень', $totalOrders)
                ->icon('heroicon-o-shopping-cart')
                ->color('info'),

            Stat::make('Нових замовлень', $newOrders)
                ->icon('heroicon-o-plus-circle')
                ->color('success'),

            Stat::make('Кількість неоплачених замовлень', $unpaidOrders)
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning'),

            Stat::make('Скасовано замовлень', $cancelledOrders)
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('Всього клієнтів', $totalMembers)
                ->icon('heroicon-o-users')
                ->color('info'),

            Stat::make('Клієнтів з боргами', $membersWithDebt)
                ->icon('heroicon-o-user-group')
                ->color('warning'),

            Stat::make('Загальна сума замовлень', number_format($totalOrderAmount, 2, ',', ' ') . ' ₴')
                ->icon('heroicon-o-currency-dollar')
                ->color('primary'),

            Stat::make('Загальний борг', number_format($totalRemainingDebt, 2, ',', ' ') . ' ₴')
                ->icon('heroicon-o-banknotes')
                ->color('danger'),

            Stat::make('Загальна переплата', number_format($totalBalance, 2, ',', ' ') . ' ₴')
                ->icon('heroicon-o-wallet')
                ->color($totalBalance >= 0 ? 'success' : 'danger')
                ->description($totalBalance >= 0 ? 'Клієнти переплатили' : 'Клієнти недоплатили'),
        ];
    }
}
