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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Назва варіанту оплати
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete(); // Зв'язок з касою
            $table->text('details')->nullable(); // Реквізити для оплати
            $table->boolean('is_active')->default(true); // Чи активний варіант
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
