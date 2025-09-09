<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Widgets\Widget;

class MemberFinancialInfo extends Widget
{
    protected static string $view = 'filament.widgets.member-financial-info';
    
    protected int | string | array $columnSpan = 'full';
    
    public ?Member $member = null;
    
    public function mount(?Member $member = null): void
    {
        $this->member = $member;
    }
    
    public static function canView(): bool
    {
        // Віджет відображається тільки на сторінках редагування клієнтів
        // Перевіряємо, чи поточний маршрут є сторінкою редагування клієнта
        $currentRoute = request()->route();
        if (!$currentRoute) {
            return false;
        }
        
        $routeName = $currentRoute->getName();
        
        // Дозволяємо тільки на сторінках редагування клієнтів
        return str_contains($routeName, 'filament.admin.resources.members.edit') || 
               str_contains($routeName, 'filament.admin.resources.members.view');
    }
    
    
    public function getFinancialData(): array
    {
        if (!$this->member) {
            return [
                'balance' => 0,
                'total_orders_amount' => 0,
                'total_orders_count' => 0,
                'formatted_balance' => '0.00₴',
                'formatted_amount' => '0.00₴',
            ];
        }
        
        // Завантажуємо debtAccount якщо не завантажено
        if (!$this->member->relationLoaded('debtAccount')) {
            $this->member->load('debtAccount');
        }
        
        $balance = $this->member->debtAccount?->balance ?? 0;
        $totalAmount = $this->member->total_orders_amount;
        $totalCount = $this->member->total_orders_count;
        
        // Форматуємо баланс
        if ($balance > 0) {
            $formattedBalance = '+' . number_format($balance, 2, ',', ' ') . '₴';
        } elseif ($balance < 0) {
            $formattedBalance = number_format($balance, 2, ',', ' ') . '₴';
        } else {
            $formattedBalance = '0.00₴';
        }
        
        return [
            'balance' => $balance,
            'total_orders_amount' => $totalAmount,
            'total_orders_count' => $totalCount,
            'formatted_balance' => $formattedBalance,
            'formatted_amount' => number_format($totalAmount, 2, ',', ' ') . '₴',
        ];
    }
}
