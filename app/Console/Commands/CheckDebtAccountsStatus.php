<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\DebtAccount;
use Illuminate\Console\Command;

class CheckDebtAccountsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debt:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Перевіряє стан рахунків заборгованості всіх клієнтів';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Перевіряємо стан рахунків заборгованості...');

        $totalMembers = Member::count();
        $membersWithDebtAccounts = Member::whereHas('debtAccount')->count();
        $membersWithoutDebtAccounts = Member::whereDoesntHave('debtAccount')->count();

        $this->info("📊 Загальна статистика:");
        $this->line("   • Всього клієнтів: {$totalMembers}");
        $this->line("   • З рахунками заборгованості: {$membersWithDebtAccounts}");
        $this->line("   • Без рахунків заборгованості: {$membersWithoutDebtAccounts}");

        if ($membersWithoutDebtAccounts > 0) {
            $this->warn("⚠️ Знайдено {$membersWithoutDebtAccounts} клієнтів без рахунків заборгованості!");
            
            $this->info("\n📋 Клієнти без рахунків заборгованості:");
            $membersWithoutAccounts = Member::whereDoesntHave('debtAccount')->get();
            foreach ($membersWithoutAccounts as $member) {
                $this->line("   • {$member->full_name} (ID: {$member->id})");
            }
            
            $this->newLine();
            $this->info("💡 Для створення рахунків заборгованості запустіть:");
            $this->line("   php artisan debt:create-accounts");
        } else {
            $this->info("✅ У всіх клієнтів є рахунки заборгованості!");
        }

        // Додаткова статистика по рахунках заборгованості
        if ($membersWithDebtAccounts > 0) {
            $this->newLine();
            $this->info("💰 Статистика по рахунках заборгованості:");
            
            $debtAccounts = DebtAccount::with('member')->get();
            $totalDebt = $debtAccounts->sum('total_debt');
            $totalPaid = $debtAccounts->sum('paid_amount');
            $totalRemaining = $debtAccounts->sum('remaining_debt');
            $totalBalance = $debtAccounts->sum('balance');
            
            $this->line("   • Загальний борг: {$totalDebt}₴");
            $this->line("   • Сплачено: {$totalPaid}₴");
            $this->line("   • Залишок: {$totalRemaining}₴");
            $this->line("   • Баланс клієнтів: {$totalBalance}₴");
            
            // Топ клієнтів по балансу
            $topClients = $debtAccounts->sortByDesc('balance')->take(5);
            if ($topClients->isNotEmpty()) {
                $this->newLine();
                $this->info("🏆 Топ клієнтів по балансу:");
                foreach ($topClients as $account) {
                    $balance = $account->balance;
                    $sign = $balance > 0 ? '+' : '';
                    $this->line("   • {$account->member->full_name}: {$sign}{$balance}₴");
                }
            }
        }

        return 0;
    }
}