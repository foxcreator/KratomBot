<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_option_id')->nullable()->after('product_id')->constrained('product_options')->onDelete('set null');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_option_id')->nullable()->after('product_id')->constrained('product_options')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_option_id']);
            $table->dropColumn('product_option_id');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_option_id']);
            $table->dropColumn('product_option_id');
        });
    }
}; 