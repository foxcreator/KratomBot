<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('telegram.show_sales_group', false);
        $this->migrator->add('telegram.show_money_group', false);
        $this->migrator->add('telegram.show_stock_group', true);
    }
};
