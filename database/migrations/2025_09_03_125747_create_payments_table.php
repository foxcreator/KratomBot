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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_account_id')->constrained('debt_accounts')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->decimal('amount', 10, 2)->comment('Сума платежу');
            $table->foreignId('payment_type_id')->constrained('payment_types');
            $table->foreignId('cash_register_id')->constrained('cash_registers');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->string('receipt_number')->nullable()->comment('Номер квитанції');
            $table->timestamps();

            $table->index(['debt_account_id', 'payment_date']);
            $table->index(['order_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};