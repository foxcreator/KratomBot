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
        Schema::table('product_options', function (Blueprint $table) {
            $table->boolean('in_stock')->default(false)->after('price');
            $table->integer('current_quantity')->default(0)->after('in_stock');
            $table->decimal('current_purchase_price', 10, 2)->nullable()->after('current_quantity');
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('current_purchase_price');
            $table->decimal('retail_price', 10, 2)->nullable()->after('wholesale_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->dropColumn('in_stock');
            $table->dropColumn('current_quantity');
            $table->dropColumn('current_purchase_price');
            $table->dropColumn('wholesale_price');
            $table->dropColumn('retail_price');
        });
    }
};
