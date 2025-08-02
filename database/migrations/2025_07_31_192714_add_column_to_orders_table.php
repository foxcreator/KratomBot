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
            $table->unsignedBigInteger('payment_type_id')->nullable()->after('id');
            $table->unsignedBigInteger('cash_register_id')->nullable()->after('payment_type_id');

            $table->foreign('payment_type_id')->references('id')->on('payment_types');
            $table->foreign('cash_register_id')->references('id')->on('cash_registers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_type_id']);
            $table->dropForeign(['cash_register_id']);
        });
    }
};
