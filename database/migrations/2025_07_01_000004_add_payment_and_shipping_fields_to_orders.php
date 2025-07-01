<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_type')->nullable()->after('source');
            $table->string('payment_receipt')->nullable()->after('payment_type');
            $table->string('shipping_phone')->nullable()->after('notes');
            $table->string('shipping_city')->nullable()->after('shipping_phone');
            $table->string('shipping_carrier')->nullable()->after('shipping_city');
            $table->string('shipping_office')->nullable()->after('shipping_carrier');
            $table->string('shipping_name')->nullable()->after('shipping_office');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_type',
                'payment_receipt',
                'shipping_phone',
                'shipping_city',
                'shipping_carrier',
                'shipping_office',
                'shipping_name',
            ]);
        });
    }
}; 