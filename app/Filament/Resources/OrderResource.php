<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\CashRegister;
use App\Models\Member;
use App\Models\Order;
use App\Models\PaymentType;
use App\Services\TelegramService;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Замовлення';
    protected static ?string $label = 'Замовлення';
    protected static ?string $pluralLabel = 'Замовлення';
    protected static ?string $navigationGroup = 'Продажі';
    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('user_id')
                    ->hidden()
                    ->default(auth()->user()->id)
                    ->numeric(),
                Forms\Components\TextInput::make('order_number')
                    ->label('Номер Замовлення')
                    ->maxLength(255)
                    ->helperText('Заповнюєтся автоматично'),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options(Order::STATUSES)
                    ->required()
                    ->default(Order::STATUSES[Order::STATUS_NEW])
                    ->disabled(fn (string $context) => $context === 'create'),
                Select::make('member_id')
                    ->label('Клієнт')
                    ->relationship('member', 'full_name') // просто ID
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('full_name')
                            ->label('Імʼя')
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

                        TextInput::make('address')
                            ->label('Адреса')
                            ->nullable(),

                        TextInput::make('city')
                            ->label('Місто')
                            ->nullable(),

                        TextInput::make('shipping_office')
                            ->label('Відділення Нової пошти')
                            ->nullable(),
                    ])
                    ->reactive()
                    ->afterStateUpdated(function (?int $state, callable $set) {
                        if (! $state) {
                            return;
                        }

                        $member = \App\Models\Member::find($state);

                        if ($member) {
                            $set('shipping_name', $member->full_name ?? '');
                            $set('shipping_phone', $member->phone ?? '');
                            $set('shipping_city', $member->city ?? '');
                            $set('shipping_office', $member->shipping_office ?? '');
                        }
                    }),
                Forms\Components\TextInput::make('source')
                    ->label('Джерело')
                    ->required()
                    ->maxLength(255)
                    ->readOnly()
                    ->default('Пряме замовлення'),
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('total_amount')
                        ->label('Сума')
                        ->readOnly()
                        ->required()
                        ->numeric()
                        ->default(0.00),
                    Forms\Components\TextInput::make('discount_percent')
                        ->label('Знижка %')
                        ->numeric()
                        ->default(0.00),
                    Forms\Components\TextInput::make('discount_amount')
                        ->label('Сума знижки')
                        ->numeric()
                        ->readOnly()
                        ->default(0.00),
                ])
                ->columns(3),
                Forms\Components\Select::make('payment_type_id')
                    ->label('Тип оплати')
                    ->options(PaymentType::pluck('name', 'id'))
                    ->reactive()
                    ->required(),

                Forms\Components\Select::make('cash_register_id')
                    ->label('Каса')
                    ->options(fn (callable $get) =>
                    CashRegister::where('payment_type_id', $get('payment_type_id'))
                        ->pluck('name', 'id')
                    )
                    ->required()
                    ->reactive()
                    ->disabled(fn (callable $get) => blank($get('payment_type_id')))
                    ->hint('Каси підтягуються за типом оплати'),

                FileUpload::make('payment_receipt')
                    ->label('Фото квитанції')
                    ->image()
                    ->directory('receipts')
                    ->imagePreviewHeight('200')
                    ->preserveFilenames()
                    ->maxSize(4096)
                    ->required(false),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Номер замовлення')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.username')
                    ->label('Нікнейм замовника')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Імʼя замовника')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('statusName')
                    ->label('Статус')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Сума')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shipping_phone')
                    ->label('Телефон отримувача')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Знижка')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Тип оплати')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->actions([
                Action::make('showReceipt')
                    ->label('Квитанція')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Квитанція')
                    ->visible(fn ($record) => $record->payment_receipt !== null)
                    ->modalSubmitAction(false) // <- ВАЖЛИВО: прибирає кнопку "Відправити"
                    ->modalCancelActionLabel('Закрити')
                    ->modalContent(function ($record) {
                        $receiptUrl = asset('storage/' . ltrim($record->payment_receipt, '/'));
                        return view('components.receipt-modal', [
                            'receiptUrl' => $receiptUrl,
                        ]);
                    }),
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
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderItemsRelationManager::class,
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
