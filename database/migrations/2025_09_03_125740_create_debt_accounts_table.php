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
        Schema::create('debt_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->decimal('total_debt', 10, 2)->default(0)->comment('Загальна сума боргу');
            $table->decimal('paid_amount', 10, 2)->default(0)->comment('Сплачена сума');
            $table->decimal('remaining_debt', 10, 2)->default(0)->comment('Залишок боргу');
            $table->enum('status', ['active', 'closed', 'overdue'])->default('active');
            $table->timestamp('last_payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_accounts');
    }
};