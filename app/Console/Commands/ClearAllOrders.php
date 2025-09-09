<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\DebtAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAllOrders extends Command
{
    protected $signature = 'orders:clear-all 
                            {--force : Force deletion without confirmation}
                            {--keep-debt-accounts : Keep debt accounts intact}
                            {--disable-fk-checks : Disable foreign key checks for faster deletion}';

    protected $description = 'Clear all orders and related data from the database';

    public function handle()
    {
        if (!$this->option('force')) {
            $this->warn('⚠️  WARNING: This will delete ALL orders and related data!');
            $this->warn('This action cannot be undone!');
            
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('🧹 Starting cleanup of orders and related data...');

        try {
            // 1. Count current data
            $orderCount = Order::count();
            $orderItemCount = OrderItem::count();
            $paymentCount = Payment::count();
            $debtAccountCount = DebtAccount::count();

            $this->info("📊 Current data:");
            $this->info("  - Orders: {$orderCount}");
            $this->info("  - Order Items: {$orderItemCount}");
            $this->info("  - Payments: {$paymentCount}");
            $this->info("  - Debt Accounts: {$debtAccountCount}");

            // 2. Delete in correct order (respecting foreign key constraints)
            if ($this->option('disable-fk-checks')) {
                $this->info('🔓 Disabling foreign key checks...');
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            $this->info('🗑️  Deleting order items...');
            OrderItem::query()->delete();
            $this->info('✅ Order items deleted');

            $this->info('🗑️  Deleting payments...');
            Payment::query()->delete();
            $this->info('✅ Payments deleted');

            $this->info('🗑️  Deleting orders...');
            Order::query()->delete();
            $this->info('✅ Orders deleted');

            if ($this->option('disable-fk-checks')) {
                $this->info('🔒 Re-enabling foreign key checks...');
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            // 3. Handle debt accounts
            if (!$this->option('keep-debt-accounts')) {
                $this->info('🗑️  Resetting debt accounts...');
                DebtAccount::query()->update([
                    'total_debt' => 0,
                    'paid_amount' => 0,
                    'remaining_debt' => 0,
                    'balance' => 0,
                    'status' => 'active',
                    'updated_at' => now(),
                ]);
                $this->info('✅ Debt accounts reset');
            } else {
                $this->info('ℹ️  Debt accounts kept intact');
            }

            // 4. Reset auto-increment counters
            $this->info('🔄 Resetting auto-increment counters...');
            DB::statement('ALTER TABLE orders AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE order_items AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE payments AUTO_INCREMENT = 1');
            $this->info('✅ Auto-increment counters reset');

            $this->info('🎉 Cleanup completed successfully!');
            $this->info('📊 Final counts:');
            $this->info("  - Orders: " . Order::count());
            $this->info("  - Order Items: " . OrderItem::count());
            $this->info("  - Payments: " . Payment::count());
            $this->info("  - Debt Accounts: " . DebtAccount::count());

        } catch (\Exception $e) {
            $this->error('❌ Error during cleanup: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
