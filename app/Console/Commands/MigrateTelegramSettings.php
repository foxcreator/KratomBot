<?php

namespace App\Console\Commands;

use App\Settings\TelegramSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateTelegramSettings extends Command
{
    protected $signature = 'migrate:telegram-settings';
    protected $description = 'Міграція налаштувань з таблиці old_settings в TelegramSetting';

    public function handle(): int
    {
        $oldSettings = DB::table('settings_old')->pluck('value', 'key')->toArray();

        $settings = app(TelegramSetting::class);

        $migrated = [];

        foreach ($oldSettings as $key => $value) {

            $property = Str::snake($key);
            if (property_exists($settings, $property)) {
                $settings->{$property} = $value;
                $migrated[] = $key;
            } else {
                $this->warn("Пропущено: ключ `{$key}` `{$property}` не знайдено в TelegramSetting");
            }
        }

        $settings->save();

        $this->info('✅ Міграція завершена!');
        $this->info('👉 Перенесені поля: ' . implode(', ', $migrated));

        return self::SUCCESS;
    }
}
