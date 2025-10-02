<?php

namespace App\Filament\Pages;

use App\Settings\TelegramSettings;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageTelegram extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Налаштування бота';

    protected static ?string $navigationGroup = 'Налаштування';
    protected ?string $heading = 'Налаштування бота';
    protected static ?int $navigationSort = 15;


    protected static string $settings = TelegramSettings::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('hello_message')
                    ->label('Вітання')
                    ->rows(3)
                    ->required(),

                Textarea::make('channel')
                    ->label('Текст переходу в Telegram канал')
                    ->rows(4),

                Textarea::make('how_ordering')
                    ->label('Як замовити')
                    ->rows(4),

                Textarea::make('payment')
                    ->label('Оплата')
                    ->rows(4),

                Textarea::make('payments')
                    ->label('Реквізити для оплати')
                    ->rows(4),

                Textarea::make('reviews')
                    ->label('Відгуки')
                    ->rows(4),

                TextInput::make('telegram_channel_discount')
                    ->label('Знижка для підписників Telegram-каналу (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0),

                Textarea::make('discount_info')
                    ->label("Текст для меню 'Отримай знижку'")
                    ->rows(4),

                TextInput::make('telegram_channel_username')
                    ->label('Username Telegram-каналу (наприклад, @auraaashopp)')
                    ->maxLength(255),
            ]);
    }
}
