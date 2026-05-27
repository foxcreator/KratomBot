<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('bot_started_at')->nullable()->after('is_subscribed');
            $table->timestamp('last_interaction_at')->nullable()->after('bot_started_at');
            $table->timestamp('channel_joined_at')->nullable()->after('last_interaction_at');
            $table->string('channel_join_source', 32)->nullable()->after('channel_joined_at');
            $table->timestamp('channel_link_clicked_at')->nullable()->after('channel_join_source');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'bot_started_at',
                'last_interaction_at',
                'channel_joined_at',
                'channel_join_source',
                'channel_link_clicked_at',
            ]);
        });
    }
};
