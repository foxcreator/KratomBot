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
            $table->foreignId('debt_account_id')->nullable()->after('member_id')->constrained('debt_accounts')->onDelete('set null');
            $table->decimal('final_amount', 10, 2)->nullable()->after('total_amount')->comment('Фінальна сума до сплати з урахуванням знижок');
            $table->decimal('paid_amount', 10, 2)->default(0)->after('final_amount')->comment('Сплачена сума по замовленню');
            $table->decimal('remaining_amount', 10, 2)->default(0)->after('paid_amount')->comment('Залишок до сплати');
            $table->enum('payment_status', ['unpaid', 'partial_paid', 'paid', 'overpaid'])->default('unpaid')->after('remaining_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['debt_account_id']);
            $table->dropColumn([
                'debt_account_id',
                'final_amount', 
                'paid_amount',
                'remaining_amount',
                'payment_status'
            ]);
        });
    }
};