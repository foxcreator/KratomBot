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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_type_id']);
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn(['payment_type_id', 'cash_register_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_type_id')->nullable()->constrained('payment_types')->nullOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
        });
    }
};