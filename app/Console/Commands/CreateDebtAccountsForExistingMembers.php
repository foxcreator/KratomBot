<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\DebtAccount;
use Illuminate\Console\Command;

class CreateDebtAccountsForExistingMembers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debt:create-accounts {--force : Force creation even if accounts exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Створює рахунки заборгованості для всіх клієнтів, які їх не мають';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Перевіряємо клієнтів без рахунків заборгованості...');

        $membersWithoutDebtAccounts = Member::whereDoesntHave('debtAccount')->get();
        
        if ($membersWithoutDebtAccounts->isEmpty()) {
            $this->info('✅ У всіх клієнтів вже є рахунки заборгованості!');
            return 0;
        }

        $this->info("📊 Знайдено {$membersWithoutDebtAccounts->count()} клієнтів без рахунків заборгованості");

        if (!$this->option('force')) {
            if (!$this->confirm('Продовжити створення рахунків заборгованості?')) {
                $this->info('❌ Операцію скасовано');
                return 0;
            }
        }

        $this->info('🚀 Створюємо рахунки заборгованості...');

        $bar = $this->output->createProgressBar($membersWithoutDebtAccounts->count());
        $bar->start();

        $created = 0;
        $errors = 0;

        foreach ($membersWithoutDebtAccounts as $member) {
            try {
                DebtAccount::create([
                    'member_id' => $member->id,
                    'total_debt' => 0,
                    'paid_amount' => 0,
                    'remaining_debt' => 0,
                    'balance' => 0,
                    'status' => 'active',
                ]);
                $created++;
            } catch (\Exception $e) {
                $this->error("❌ Помилка при створенні рахунку для клієнта {$member->full_name}: {$e->getMessage()}");
                $errors++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($created > 0) {
            $this->info("✅ Успішно створено {$created} рахунків заборгованості");
        }

        if ($errors > 0) {
            $this->warn("⚠️ Виникло {$errors} помилок при створенні рахунків");
        }

        // Перевіряємо результат
        $remainingWithoutAccounts = Member::whereDoesntHave('debtAccount')->count();
        if ($remainingWithoutAccounts === 0) {
            $this->info('🎉 У всіх клієнтів тепер є рахунки заборгованості!');
        } else {
            $this->warn("⚠️ Залишилось {$remainingWithoutAccounts} клієнтів без рахунків заборгованості");
        }

        return 0;
    }
}