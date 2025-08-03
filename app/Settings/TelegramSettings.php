<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class TelegramSetting extends Settings
{
    public string $hello_message;
    public string $channel;
    public string $how_ordering;
    public string $payment;
    public string $payments;
    public string $reviews;
    public int $telegram_channel_discount;
    public string $discount_info;
    public string $telegram_channel_username;

    public static function group(): string
    {
        return 'telegram';
    }
}
