<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\DebtAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTestOrders extends Command
{
    protected $signature = 'orders:create-test 
                            {--count=5 : Number of test orders to create}
                            {--force : Force creation without confirmation}';

    protected $description = 'Create test orders with sample data';

    public function handle()
    {
        $count = (int) $this->option('count');
        
        if (!$this->option('force')) {
            $this->info("📦 Will create {$count} test orders");
            
            if (!$this->confirm('Do you want to continue?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('🏗️  Creating test orders...');

        try {
            DB::beginTransaction();

            // Get existing members and products
            $members = Member::all();
            $products = Product::with('options')->get();

            if ($members->isEmpty()) {
                $this->error('❌ No members found. Please create members first.');
                return 1;
            }

            if ($products->isEmpty()) {
                $this->error('❌ No products found. Please create products first.');
                return 1;
            }

            $this->info("👥 Found {$members->count()} members");
            $this->info("📦 Found {$products->count()} products");

            for ($i = 1; $i <= $count; $i++) {
                $this->info("Creating order {$i}/{$count}...");

                // Create order
                $order = Order::create([
                    'order_number' => 'TEST-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                    'member_id' => $members->random()->id,
                    'status' => $this->getRandomStatus(),
                    'total_amount' => 0, // Will be calculated
                    'discount_percent' => rand(0, 20),
                    'discount_amount' => 0, // Will be calculated
                    'shipping_name' => 'Test Customer ' . $i,
                    'shipping_phone' => '+380' . rand(100000000, 999999999),
                    'shipping_city' => $this->getRandomCity(),
                    'shipping_carrier' => 'Нова пошта',
                    'shipping_office' => 'Відділення ' . rand(1, 100),
                    'notes' => 'Тестове замовлення #' . $i,
                    'source' => 'test_command',
                ]);

                // Create order items
                $itemCount = rand(1, 4);
                $subtotal = 0;

                for ($j = 1; $j <= $itemCount; $j++) {
                    $product = $products->random();
                    $option = $product->options->random();
                    $quantity = rand(1, 3);
                    $price = $option->price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_option_id' => $option->id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total' => $price * $quantity,
                    ]);

                    $subtotal += $price * $quantity;
                }

                // Update order totals
                $discountAmount = ($subtotal * $order->discount_percent) / 100;
                $totalAmount = $subtotal - $discountAmount;

                $order->update([
                    'total_amount' => $totalAmount,
                    'discount_amount' => $discountAmount,
                ]);

                $this->info("  ✅ Order {$order->order_number} created (Total: {$totalAmount}₴)");
            }

            // Update debt accounts
            $this->info('🔄 Updating debt accounts...');
            $this->updateDebtAccounts();

            DB::commit();

            $this->info('🎉 Test orders created successfully!');
            $this->info("📊 Created {$count} orders with items and updated debt accounts");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error creating test orders: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function getRandomStatus(): string
    {
        $statuses = ['new', 'pending_payment', 'paid', 'processing', 'completed'];
        return $statuses[array_rand($statuses)];
    }

    private function getRandomCity(): string
    {
        $cities = ['Київ', 'Харків', 'Одеса', 'Дніпро', 'Львів', 'Запоріжжя', 'Кривий Ріг'];
        return $cities[array_rand($cities)];
    }

    private function updateDebtAccounts(): void
    {
        $members = Member::with('orders')->get();

        foreach ($members as $member) {
            $debtAccount = $member->debtAccount;
            if (!$debtAccount) {
                $debtAccount = DebtAccount::create([
                    'member_id' => $member->id,
                    'total_debt' => 0,
                    'paid_amount' => 0,
                    'remaining_debt' => 0,
                    'balance' => 0,
                    'status' => 'active',
                ]);
            }

            $totalDebt = $member->orders()->sum('total_amount');
            $paidAmount = 0; // For test data, no payments
            $remainingDebt = $totalDebt - $paidAmount;
            $balance = $paidAmount - $totalDebt;

            $debtAccount->update([
                'total_debt' => $totalDebt,
                'paid_amount' => $paidAmount,
                'remaining_debt' => $remainingDebt,
                'balance' => $balance,
            ]);
        }
    }
}
