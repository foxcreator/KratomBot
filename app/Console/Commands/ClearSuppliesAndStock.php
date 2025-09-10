<?php

namespace App\Console\Commands;

use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\ProductOption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearSuppliesAndStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:supplies-stock 
                            {--force : Виконати без підтвердження}
                            {--dry-run : Показати що буде видалено без виконання}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистити всі поставки постачальників та скинути кількість товарів на складі';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Команда очищення поставок та складу');
        $this->newLine();

        // Перевіряємо опції
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 РЕЖИМ ПЕРЕГЛЯДУ - нічого не буде видалено');
            $this->newLine();
        }

        // Підраховуємо кількість записів для видалення
        $suppliesCount = Supply::count();
        $supplyItemsCount = SupplyItem::count();
        $productOptionsWithStock = ProductOption::where('in_stock', true)->count();

        $this->info('📊 Статистика для очищення:');
        $this->table(
            ['Тип даних', 'Кількість записів'],
            [
                ['Поставки (Supply)', $suppliesCount],
                ['Елементи поставок (SupplyItem)', $supplyItemsCount],
                ['Опції товарів з товаром на складі', $productOptionsWithStock],
            ]
        );

        if ($suppliesCount === 0 && $supplyItemsCount === 0) {
            $this->info('✅ Немає поставок для очищення');
            return 0;
        }

        // Показуємо попередження
        $this->warn('⚠️  УВАГА! Ця команда:');
        $this->warn('   • Видалить всі поставки постачальників');
        $this->warn('   • Видалить всі елементи поставок');
        $this->warn('   • Скине кількість товарів на складі до 0');
        $this->warn('   • НЕ торкнеться самих опцій товарів');
        $this->warn('   • Ця дія НЕЗВОРОТНА!');
        $this->newLine();

        if (!$force && !$dryRun) {
            if (!$this->confirm('Ви впевнені, що хочете продовжити?')) {
                $this->info('❌ Операцію скасовано');
                return 0;
            }
        }

        if ($dryRun) {
            $this->info('🔍 Режим перегляду завершено');
            return 0;
        }

        // Виконуємо очищення в транзакції
        try {
            DB::transaction(function () {
                $this->info('🔄 Починаємо очищення...');

                // 1. Спочатку видаляємо посилання на supply_item_id в order_items
                $this->info('   • Очищаємо посилання в замовленнях...');
                DB::table('order_items')->update(['supply_item_id' => null]);
                $this->info('   ✅ Посилання в замовленнях очищено');

                // 2. Видаляємо всі елементи поставок
                $this->info('   • Видаляємо елементи поставок...');
                SupplyItem::query()->delete();
                $this->info('   ✅ Елементи поставок видалено');

                // 3. Видаляємо всі поставки
                $this->info('   • Видаляємо поставки...');
                Supply::query()->delete();
                $this->info('   ✅ Поставки видалено');

                // 4. Скидаємо кількість товарів на складі (НЕ видаляємо опції!)
                $this->info('   • Скидаємо кількість товарів на складі...');
                ProductOption::query()->update([
                    'in_stock' => false,
                    'current_quantity' => 0,
                    'current_purchase_price' => 0,
                ]);
                $this->info('   ✅ Кількість товарів скинуто (опції залишилися)');

                $this->info('✅ Очищення завершено успішно!');
            });

        } catch (\Exception $e) {
            $this->error('❌ Помилка під час очищення: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('🎉 Всі поставки очищено, склад скинуто!');
        $this->info('💡 Опції товарів залишилися, тепер ви можете створити нові поставки');

        return 0;
    }
}