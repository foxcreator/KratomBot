<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member;
use App\Models\DebtAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\OrderItem;

class DebtSystemDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Створюємо типи оплат та каси якщо їх немає
        $paymentType = PaymentType::firstOrCreate(['name' => 'Готівка']);
        $cashRegister = CashRegister::firstOrCreate([
            'name' => 'Основна каса',
            'payment_type_id' => $paymentType->id,
            'balance' => 0,
            'details' => 'Основна каса для готівкових платежів'
        ]);

        // Створюємо тестового клієнта
        $member = Member::firstOrCreate([
            'telegram_id' => 123456789,
            'username' => 'test_client',
            'full_name' => 'Тестовий Клієнт',
            'phone' => '+380123456789'
        ]);

        // Створюємо рахунок заборгованості
        $debtAccount = DebtAccount::firstOrCreate([
            'member_id' => $member->id,
            'total_debt' => 0,
            'paid_amount' => 0,
            'remaining_debt' => 0,
            'status' => 'active'
        ]);

        // Створюємо тестове замовлення
        $product = Product::first();
        if ($product) {
            $order = Order::create([
                'member_id' => $member->id,
                'debt_account_id' => $debtAccount->id,
                'status' => 'new',
                'total_amount' => 1000.00,
                'final_amount' => 1000.00,
                'paid_amount' => 0.00,
                'remaining_amount' => 1000.00,
                'payment_status' => 'unpaid',
                'source' => 'Пряме замовлення',
                'payment_type_id' => $paymentType->id,
                'cash_register_id' => $cashRegister->id,
                'shipping_name' => 'Тестовий Клієнт',
                'shipping_phone' => '+380123456789',
                'shipping_city' => 'Київ',
                'shipping_carrier' => 'Нова Пошта',
                'shipping_office' => '1',
                'notes' => 'Тестове замовлення для демонстрації системи заборгованості'
            ]);

            // Додаємо товар до замовлення
            if ($product->options()->exists()) {
                $option = $product->options()->first();
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_option_id' => $option->id,
                    'quantity' => 1,
                    'price' => 1000.00
                ]);
            } else {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 1000.00
                ]);
            }

            // Оновлюємо загальний борг клієнта
            $debtAccount->update([
                'total_debt' => 1000.00,
                'remaining_debt' => 1000.00
            ]);

            $this->command->info('Створено демонстраційні дані:');
            $this->command->info('- Клієнт: ' . $member->full_name);
            $this->command->info('- Замовлення: ' . $order->order_number);
            $this->command->info('- Сума до сплати: ' . $order->total_amount . ' грн');
            $this->command->info('- Статус оплати: ' . $order->paymentStatusName);
            $this->command->info('');
            $this->command->info('Тепер ви можете:');
            $this->command->info('1. Перейти в "Заборгованість" та додати платіж');
            $this->command->info('2. Перейти в "Замовлення" та подивитися статус оплати');
            $this->command->info('3. Додати часткові платежі через "Платежі"');
        } else {
            $this->command->error('Не знайдено товарів для створення демонстраційного замовлення');
        }
    }
}