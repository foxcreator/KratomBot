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
        Schema::table('payments', function (Blueprint $table) {
            // Робимо поля nullable для списання з балансу
            $table->unsignedBigInteger('payment_type_id')->nullable()->change();
            $table->unsignedBigInteger('cash_register_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Повертаємо поля як NOT NULL
            $table->unsignedBigInteger('payment_type_id')->nullable(false)->change();
            $table->unsignedBigInteger('cash_register_id')->nullable(false)->change();
        });
    }
};
