<?php

namespace App\Filament\Pages;

use App\Settings\TelegramSettings;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Services\TelegramChannelTrackingService;
use App\Services\TelegramWebhookService;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                Section::make('Відображення розділів меню')
                    ->description('Оберіть, які групи розділів показувати в бічному меню адмін-панелі')
                    ->schema([
                        Toggle::make('show_sales_group')
                            ->label('Продажі (Замовлення, Платежі, Клієнти, Борги)')
                            ->default(false),
                        Toggle::make('show_money_group')
                            ->label('Гроші (Каси, Зняття коштів, Способи оплати, Типи оплат)')
                            ->default(false),
                        Toggle::make('show_stock_group')
                            ->label('Склад (Бренди, Товари, Підкатегорії, Поставки, Залишки)')
                            ->default(true),
                        Toggle::make('show_broadcasts_group')
                            ->label('Розсилка (Розсилки, Підписники)')
                            ->default(true),
                    ])
                    ->columns(1)
                    ->collapsible(),

                Section::make('Telegram бот')
                    ->schema([
                        Toggle::make('start_only_mode')
                            ->label('Тимчасово: лише стартове повідомлення')
                            ->helperText('Увімкнено: зберігаємо користувача, одне повідомлення з поля «Вітання», без клавіатури та другого повідомлення про канал. Інші команди та кнопки ігноруються.')
                            ->default(false),

                        Textarea::make('hello_message')
                            ->label('Вітання')
                            ->rows(3)
                            ->required(),

                        Textarea::make('channel')
                            ->label('Текст переходу в Telegram канал')
                            ->rows(4)
                            ->nullable(),

                        Textarea::make('how_ordering')
                            ->label('Як замовити')
                            ->rows(4)
                            ->nullable(),

                        Textarea::make('payment')
                            ->label('Оплата')
                            ->rows(4)
                            ->nullable(),

                        Textarea::make('payments')
                            ->label('Реквізити для оплати')
                            ->rows(4)
                            ->nullable(),

                        Textarea::make('reviews')
                            ->label('Відгуки')
                            ->rows(4)
                            ->nullable(),

                        TextInput::make('telegram_channel_discount')
                            ->label('Знижка для підписників Telegram-каналу (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0),

                        Textarea::make('discount_info')
                            ->label("Текст для меню 'Отримай знижку'")
                            ->rows(4)
                            ->nullable(),

                        TextInput::make('telegram_channel_username')
                            ->label('Username Telegram-каналу (наприклад, @auraaashopp)')
                            ->helperText('Для публічного каналу. Для приватного можна залишити порожнім і вказати ID каналу нижче.')
                            ->maxLength(255)
                            ->nullable(),

                        TextInput::make('telegram_channel_chat_id')
                            ->label('ID Telegram-каналу (для приватного)')
                            ->helperText('Приклад: -1001234567890. Якщо вказаний ID, username не обовʼязковий.')
                            ->maxLength(255)
                            ->nullable(),

                        TextInput::make('bot_channel_invite_link')
                            ->label('Invite link для трекінгу підписок')
                            ->helperText('Генерується кнопкою «Створити invite link». Користувачі мають підписуватись саме по цьому посиланню з бота.')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn () => app(TelegramSettings::class)->bot_channel_invite_link),

                        TextInput::make('webhook_url')
                            ->label('URL webhook')
                            ->helperText('Оновлюється кнопкою «Оновити webhook». Має збігатися з APP_URL на сервері.')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn () => rtrim((string) config('app.url'), '/') . '/telegram/webhook'),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateInviteLink')
                ->label('Створити invite link')
                ->icon('heroicon-o-link')
                ->requiresConfirmation()
                ->modalDescription('Бот має бути адміном каналу з правом запрошувати користувачів.')
                ->action(function () {
                    $link = app(TelegramChannelTrackingService::class)->ensureBotInviteLink();
                    if ($link) {
                        Notification::make()
                            ->title('Invite link створено')
                            ->body($link)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Помилка')
                            ->body('Перевірте, що бот — адмін каналу з правом запрошувати. Вкажіть username каналу або ID каналу.')
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('setupWebhook')
                ->label('Оновити webhook')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Встановить webhook на APP_URL/telegram/webhook з підтримкою message, callback_query та chat_member (трекінг підписок на канал).')
                ->action(function () {
                    $result = app(TelegramWebhookService::class)->setup();
                    $description = $result['response']['description'] ?? json_encode($result['response'], JSON_UNESCAPED_UNICODE);

                    if (app(TelegramWebhookService::class)->isSuccessful($result)) {
                        Notification::make()
                            ->title('Webhook оновлено')
                            ->body($result['url'] . "\n" . $description)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Помилка webhook')
                            ->body($result['url'] . "\n" . $description)
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
