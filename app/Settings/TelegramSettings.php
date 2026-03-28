<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class TelegramSettings extends Settings
{
    public ?string $hello_message = '';
    public ?string $channel = '';
    public ?string $how_ordering = '';
    public ?string $payment = '';
    public ?string $payments = '';
    public ?string $reviews = '';
    public int $telegram_channel_discount = 0;
    public ?string $discount_info = '';
    public ?string $telegram_channel_username = '';

    public bool $show_sales_group = false;
    public bool $show_money_group = false;
    public bool $show_stock_group = true;

    public static function group(): string
    {
        return 'telegram';
    }
}
