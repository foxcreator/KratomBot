<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('telegram_id')->index();
            $table->string('event_type', 16)->index(); // join | leave
            $table->string('source', 32)->nullable()->index(); // bot | organic | unknown
            $table->timestamp('occurred_at')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_subscription_events');
    }
};
