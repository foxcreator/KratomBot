<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\CashRegister;
use App\Models\Member;
use App\Models\Order;
use App\Models\PaymentType;
use App\Services\TelegramService;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationLabel = 'Замовлення';
    protected static ?string $label = 'Замовлення';
    protected static ?string $pluralLabel = 'Замовлення';
    protected static ?string $navigationGroup = 'Продажі';
    protected static ?int $navigationSort = 0;

    protected static function isProcessing($get): bool
    {
        if (!empty($get('status')) || $get('status') !== Order::STATUS_NEW) {
            return false;
        }
        return true;
    }

    protected static function getStatuses($get): array
    {
        $statuses = Order::STATUSES;
        
        // Адміністратор може змінювати будь-який статус
        if (auth()->user()->isAdmin()) {
            return $statuses;
        }
        
        // Звичайні користувачі мають обмежені можливості
        $currentStatus = $get('status');
        
        switch ($currentStatus) {
            case Order::STATUS_NEW:
            case Order::STATUS_PENDING_PAYMENT:
                return [
                    Order::STATUS_PENDING_PAYMENT => $statuses[Order::STATUS_PENDING_PAYMENT],
                    Order::STATUS_PARTIALLY_PAID => $statuses[Order::STATUS_PARTIALLY_PAID],
                    Order::STATUS_PAID => $statuses[Order::STATUS_PAID],
                    Order::STATUS_PROCESSING => $statuses[Order::STATUS_PROCESSING],
                ];
                
            case Order::STATUS_PARTIALLY_PAID:
                return [
                    Order::STATUS_PARTIALLY_PAID => $statuses[Order::STATUS_PARTIALLY_PAID],
                    Order::STATUS_PAID => $statuses[Order::STATUS_PAID],
                    Order::STATUS_PROCESSING => $statuses[Order::STATUS_PROCESSING],
                ];
                
            case Order::STATUS_PAID:
            case Order::STATUS_PROCESSING:
                return [
                    Order::STATUS_PAID => $statuses[Order::STATUS_PAID],
                    Order::STATUS_PROCESSING => $statuses[Order::STATUS_PROCESSING],
                    Order::STATUS_COMPLETED => $statuses[Order::STATUS_COMPLETED],
                ];
                
            default:
                return $statuses;
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('user_id')
                    ->hidden()
                    ->default(auth()->user()->id)
                    ->numeric(),
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('order_number')
                        ->label('Номер Замовлення')
                        ->maxLength(255)
                        ->disabled()
                        ->helperText('Заповнюєтся автоматично'),
                    Forms\Components\Select::make('status')
                        ->label('Статус')
                        ->options(fn (callable $get) => self::getStatuses($get))
                        ->required()
                        ->default(Order::STATUS_PENDING_PAYMENT)
                        ->disabled(function (callable $get, string $context) {
                            return (
                                $context === 'create' ||
                                (!auth()->user()->isAdmin() && $get('status') === Order::STATUS_COMPLETED)
                            );
                        })
                        ->rules([]),
                    Select::make('member_id')
                        ->label('Клієнт')
                        ->required()
                        ->options(function () {
                            return Member::with('debtAccount')->get()->mapWithKeys(function ($member) {
                                $balance = $member->debtAccount?->balance ?? 0;
                                if ($balance > 0) {
                                    $balanceText = " (+{$balance}₴)";
                                } elseif ($balance < 0) {
                                    $balanceText = " ({$balance}₴)";
                                } else {
                                    $balanceText = " (0₴)";
                                }
                                return [$member->id => $member->full_name . $balanceText];
                            });
                        })
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->createOptionForm([
                            TextInput::make('full_name')
                                ->label('Ім\'я')
                                ->required(),
                            TextInput::make('phone')
                                ->label('Телефон')
                                ->tel()
                                ->required()
                                ->unique('members', 'phone'),
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->unique('members', 'email')
                                ->nullable(),
                            TextInput::make('username')
                                ->label('Нікнейм')
                                ->nullable(),
                            TextInput::make('telegram_id')
                                ->label('Telegram ID')
                                ->numeric()
                                ->nullable(),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            return Member::create($data)->id;
                        })
                        ->disabled(fn (callable $get) => self::isProcessing($get))
                        ->reactive()
                        ->afterStateUpdated(function (?int $state, callable $set) {
                            if (! $state) {
                                return;
                            }

                            $member = \App\Models\Member::with('debtAccount')->find($state);

                            if ($member) {
                                $set('shipping_name', $member->full_name ?? '');
                                $set('shipping_phone', $member->phone ?? '');
                                $set('shipping_city', $member->city ?? '');
                                $set('shipping_office', $member->shipping_office ?? '');
                            }
                        }),
                    ])
                    ->columns(3),

                Forms\Components\Placeholder::make('client_balance_info')
                    ->label('Баланс клієнта')
                    ->content(function (callable $get) {
                        $memberId = $get('member_id');
                        if (!$memberId) {
                            return 'Оберіть клієнта для перегляду балансу';
                        }
                        
                        $member = \App\Models\Member::with('debtAccount')->find($memberId);
                        if (!$member || !$member->debtAccount) {
                            return 'Баланс: 0.00₴';
                        }
                        
                        $balance = $member->debtAccount->balance;
                        if ($balance > 0) {
                            return "Баланс: +{$balance}₴";
                        } elseif ($balance < 0) {
                            return "Баланс: {$balance}₴";
                        } else {
                            return "Баланс: 0.00₴";
                        }
                    })
                    ->reactive()
                    ->visible(fn (callable $get) => !empty($get('member_id')))
                    ->columnSpanFull(),

                Forms\Components\Select::make('source')
                    ->label('Джерело замовлення')
                    ->options(Order::SOURCES)
                    ->default(Order::SOURCE_ADMIN)
                    ->required(),

                Forms\Components\Select::make('payment_type')
                    ->label('Тип оплати')
                    ->options([
                        'prepaid' => 'Передплата',
                        'cod' => 'Накладений платіж',
                    ])
                    ->default('prepaid')
                    ->nullable(),
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('total_amount')
                        ->label('Загальна сума')
                        ->readOnly()
                        ->required()
                        ->numeric()
                        ->default(0.00)
                        ->disabled(fn (callable $get) => self::isProcessing($get))
                        ->reactive(),

                    Forms\Components\TextInput::make('final_amount')
                        ->label('До оплати')
                        ->numeric()
                        ->readOnly()
                        ->default(0.00)
                        ->disabled(fn (callable $get) => self::isProcessing($get))
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Коли встановлюється "До оплати", це фінальна сума після знижки
                            // Не перераховуємо знижку тут, щоб не створювати конфлікт
                        })
                        ->afterStateHydrated(function (callable $set, callable $get) {
                            // Після завантаження форми, встановлюємо final_amount на базі total - discount
                            $total = floatval($get('total_amount'));
                            $percent = floatval($get('discount_percent'));
                            if ($percent > 0) {
                                $final = $total * (1 - $percent / 100); // Фінальна = загальна - знижка
                                $set('final_amount', round($final, 2));
                            } else {
                                $set('final_amount', $total);
                            }
                        })
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('discount_percent')
                        ->label('Знижка %')
                        ->numeric()
                        ->default(0.00)
                        ->reactive()
                        ->disabled(fn (callable $get) => self::isProcessing($get))
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Рахуємо знижку на основі total_amount
                            $total = floatval($get('total_amount'));
                            $discount = $total * ($state / 100);
                            $final = $total - $discount;
                            $set('discount_amount', round($discount, 2));
                            $set('final_amount', round($final, 2));
                        }),

                    Forms\Components\TextInput::make('discount_amount')
                        ->label('Сума знижки')
                        ->numeric()
                        ->readOnly()
                        ->default(0.00)
                        ->disabled(fn (callable $get) => self::isProcessing($get))
                        ->reactive()
                        ->afterStateHydrated(function (callable $set, callable $get) {
                            $total = floatval($get('total_amount'));
                            $percent = floatval($get('discount_percent'));
                            $discount = $total * ($percent / 100);  // Рахуємо від total_amount, а не від final_amount
                            $set('discount_amount', round($discount, 2));
                        }),
                ])
                    ->columns(4),

                Forms\Components\Section::make('Фінансові показники')
                    ->schema([
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Сплачено')
                            ->numeric()
                            ->prefix('₴')
                            ->default(0.00)
                            ->disabled(true)
                            ->helperText('Розраховується автоматично з платежів'),

                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('Залишок до сплати')
                            ->numeric()
                            ->prefix('₴')
                            ->readOnly()
                            ->default(0.00)
                            ->helperText('Розраховується автоматично'),

                        Forms\Components\TextInput::make('payment_status')
                            ->label('Статус оплати')
                            ->disabled(true)
                            ->helperText('Оновлюється автоматично при внесенні платежів')
                            ->formatStateUsing(fn ($state) => Order::PAYMENT_STATUSES[$state] ?? 'Невідомо'),
                    ])
                    ->columns(3),


                Forms\Components\Section::make('Квітанція (тільки для замовлень з Telegram бота)')
                    ->schema([
                        Forms\Components\FileUpload::make('payment_receipt')
                            ->label('Квітанція про оплату')
                            ->image()
                            ->directory('receipts')
                            ->visibility('private')
                            ->disabled()
                            ->helperText('Це поле заповнюється автоматично при створенні замовлення через Telegram бот. Для ручних платежів використовуйте розділ "Платежі".')
                            ->dehydrated(false),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('shipping_phone')
                        ->label('Номер телефону')
                        ->tel()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('shipping_city')
                        ->label('Місто')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('shipping_carrier')
                        ->label('Спосіб доставки')
                        ->maxLength(255)
                        ->default('Нова Пошта'),
                    Forms\Components\TextInput::make('shipping_office')
                        ->label('Відділеня')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('shipping_name')
                        ->label('ПІБ Замовника')
                        ->maxLength(255),
                ])
                    ->columns(2)
                    ->heading('Дані відправки'),
                Forms\Components\Textarea::make('notes')
                    ->label('Нотатки')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Основна інформація')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number')
                            ->label('Номер замовлення'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Статус')
                            ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? 'Невідомо')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'new' => 'warning',
                                'pending_payment' => 'danger',
                                'partially_paid' => 'warning',
                                'paid' => 'success',
                                'processing' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                            }),
                        Infolists\Components\TextEntry::make('member.full_name')
                            ->label('Клієнт'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Дата створення')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Фінансові показники')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_amount')
                            ->label('Загальна сума')
                            ->money('UAH'),
                        Infolists\Components\TextEntry::make('final_amount')
                            ->label('Фінальна сума')
                            ->money('UAH'),
                        Infolists\Components\TextEntry::make('paid_amount')
                            ->label('Сплачено')
                            ->money('UAH'),
                        Infolists\Components\TextEntry::make('remaining_amount')
                            ->label('Залишок')
                            ->money('UAH')
                            ->color(fn ($record) => $record->remaining_amount > 0 ? 'danger' : 'success'),
                        Infolists\Components\TextEntry::make('payment_status')
                            ->label('Статус оплати')
                            ->formatStateUsing(fn ($state) => Order::PAYMENT_STATUSES[$state] ?? 'Невідомо')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'unpaid' => 'danger',
                                'partial_paid' => 'warning',
                                'paid' => 'success',
                                'overpaid' => 'info',
                            }),
                    ])
                    ->columns(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('created_at', 'desc'))
            ->searchable()
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Номер замовлення')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.username')
                    ->label('Нікнейм замовника')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Імʼя замовника')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('statusName')
                    ->label('Статус'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус (пошук)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Сума')
                    ->money('UAH')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Сплачено')
                    ->money('UAH')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Залишок')
                    ->money('UAH')
                    ->sortable()
                    ->color(fn ($record) => $record->remaining_amount > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Статус оплати')
                    ->badge()
                    ->formatStateUsing(fn ($state) => \App\Models\Order::PAYMENT_STATUSES[$state] ?? 'Невідомо')
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partial_paid' => 'warning',
                        'paid' => 'success',
                        'overpaid' => 'info',
                    }),
                Tables\Columns\TextColumn::make('sourceName')
                    ->label('Джерело')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Адмін-панель' => 'info',
                        'Telegram бот' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('source')
                    ->label('Джерело (пошук)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shipping_phone')
                    ->label('Телефон отримувача')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Знижка')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Нотатки')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
                Tables\Columns\ImageColumn::make('payment_receipt')
                    ->label('Квітанція')
                    ->circular()
                    ->defaultImageUrl(url('/images/_blank.png'))
                    ->url(fn ($record) => $record->payment_receipt ? asset('storage/' . $record->payment_receipt) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->payment_receipt)),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('оновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order_number', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('З ...'),
                        DatePicker::make('created_until')->label('По ...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    }),
                SelectFilter::make('status')
                    ->label('Статус замовлення')
                    ->options(Order::STATUSES)
                    ->placeholder('Всі')
                    ->searchable(),
                SelectFilter::make('payment_status')
                    ->label('Статус оплати')
                    ->options(Order::PAYMENT_STATUSES)
                    ->placeholder('Всі')
                    ->searchable(),
                SelectFilter::make('source')
                    ->label('Джерело замовлення')
                    ->options(Order::SOURCES)
                    ->placeholder('Всі')
                    ->searchable(),
            ])
            ->actions([
                // Квитанції тепер переглядаються в розділі "Платежі"
                Tables\Actions\EditAction::make(),
                Action::make('sendMessage')
                    ->label('Відправити повідомлення')
                    ->color('success')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn ($record) => $record->member->telegram_id !== null)
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Повідомлення')
                            ->required()
                            ->rows(5),
                    ])
                    ->action(function (array $data, $record) {
                        $member = $record->member;
                        if ($member?->telegram_id) {
                        app(TelegramService::class)->sendMessage($member->telegram_id, $data['message']);
                            Notification::make()
                                ->title('Повідомлення надіслано')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Telegram ID не вказано')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderItemsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
