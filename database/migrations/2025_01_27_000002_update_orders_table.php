<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Додаємо нові поля
            $table->string('order_number')->unique()->nullable()->after('id');
            $table->decimal('total_amount', 10, 2)->default(0)->after('status');
            $table->text('notes')->nullable()->after('total_amount');
            $table->string('source')->default('direct')->after('notes'); // direct, cart
            

        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->dropColumn(['order_number', 'total_amount', 'notes', 'source']);
        });
    }
}; 