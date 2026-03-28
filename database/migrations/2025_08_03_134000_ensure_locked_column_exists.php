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
        // Через попередні колізії міграція 131136 була позначена як виконана, 
        // але колонка 'locked' могла так і не з'явитися в таблиці settings від Spatie.
        // Ця міграція гарантує, що колонка існує ПЕРЕД запуском telegram_bot_settings.
        if (Schema::hasTable('settings') && !Schema::hasColumn('settings', 'locked')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->boolean('locked')->default(false)->after('payload');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'locked')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('locked');
            });
        }
    }
};
