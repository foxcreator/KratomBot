<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Видаляємо foreign key (заміни ім'я якщо інше)
            if (Schema::hasColumn('cart_items', 'product_id')) {
                $table->dropForeign('cart_items_product_id_foreign');
            }
            // Видаляємо унікальний індекс
            $table->dropUnique('cart_items_member_id_product_id_unique');
            // Додаємо foreign key назад (без unique)
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign('cart_items_product_id_foreign');
            $table->unique(['member_id', 'product_id']);
            $table->foreign('product_id')->references('id')->on('products');
        });
    }
}; 