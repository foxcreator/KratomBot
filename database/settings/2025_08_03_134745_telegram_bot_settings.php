<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('telegram.hello_message', 'Привіт 👋');
        $this->migrator->add('telegram.channel', '');
        $this->migrator->add('telegram.how_ordering', '');
        $this->migrator->add('telegram.payment', '');
        $this->migrator->add('telegram.payments', '');
        $this->migrator->add('telegram.reviews', '');
        $this->migrator->add('telegram.telegram_channel_discount', 0);
        $this->migrator->add('telegram.discount_info', '');
        $this->migrator->add('telegram.telegram_channel_username', '');
    }
};
