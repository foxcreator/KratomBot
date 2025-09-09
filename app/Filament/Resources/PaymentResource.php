<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use App\Models\DebtAccount;
use App\Models\PaymentType;
use App\Models\CashRegister;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Платежі';
    protected static ?string $label = 'Платіж';
    protected static ?string $pluralLabel = 'Платежі';
    protected static ?string $navigationGroup = 'Продажі';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('debt_account_id')
                    ->label('Рахунок заборгованості')
                    ->options(function () {
                        return \App\Models\DebtAccount::with('member')->get()->mapWithKeys(function ($debtAccount) {
                            $balance = $debtAccount->balance ?? 0;
                            $balanceText = $balance > 0 ? " (+{$balance}₴)" : ($balance < 0 ? " ({$balance}₴)" : "");
                            return [$debtAccount->id => $debtAccount->member->full_name . ' (ID: ' . $debtAccount->id . ')' . $balanceText];
                        });
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (?int $state, callable $set) {
                        if (!$state) {
                            return;
                        }
                        
                        $debtAccount = \App\Models\DebtAccount::with('member')->find($state);
                        if ($debtAccount) {
                            $set('debt_account_info', [
                                'client' => $debtAccount->member->full_name,
                                'balance' => $debtAccount->balance,
                                'total_debt' => $debtAccount->total_debt,
                                'paid_amount' => $debtAccount->paid_amount,
                                'remaining_debt' => $debtAccount->remaining_debt,
                            ]);
                        }
                    }),

                Forms\Components\Placeholder::make('debt_account_info')
                    ->label('Інформація про рахунок')
                    ->content(function (callable $get) {
                        $info = $get('debt_account_info');
                        if (!$info) {
                            return 'Оберіть рахунок заборгованості для перегляду інформації';
                        }
                        
                        $balance = $info['balance'] ?? 0;
                        $sign = $balance > 0 ? '+' : '';
                        
                        return "Клієнт: {$info['client']}\n" .
                               "Баланс: {$sign}{$balance}₴\n" .
                               "Загальний борг: {$info['total_debt']}₴\n" .
                               "Сплачено: {$info['paid_amount']}₴\n" .
                               "Залишок: {$info['remaining_debt']}₴";
                    })
                    ->visible(fn (callable $get) => !empty($get('debt_account_id')))
                    ->columnSpanFull(),

                Forms\Components\Select::make('order_id')
                    ->label('Замовлення')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->nullable(),

                Forms\Components\TextInput::make('amount')
                    ->label('Сума платежу')
                    ->numeric()
                    ->prefix('₴')
                    ->required(),

                Forms\Components\Select::make('payment_method')
                    ->label('Спосіб платежу')
                    ->options(Payment::PAYMENT_METHODS)
                    ->default(Payment::PAYMENT_METHOD_CASH)
                    ->required()
                    ->reactive()
                    ->helperText('Готівка/Переказ - внесення коштів, Списання з балансу - списання з рахунку клієнта'),

                Forms\Components\Select::make('payment_type_id')
                    ->label('Тип оплати')
                    ->relationship('paymentType', 'name')
                    ->required(),

                Forms\Components\Select::make('cash_register_id')
                    ->label('Каса')
                    ->relationship('cashRegister', 'name')
                    ->required(),

                Forms\Components\DatePicker::make('payment_date')
                    ->label('Дата платежу')
                    ->default(now())
                    ->required(),

                Forms\Components\TextInput::make('receipt_number')
                    ->label('Номер квитанції')
                    ->disabled(),

                Forms\Components\FileUpload::make('receipts')
                    ->label('Квитанції')
                    ->multiple()
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->maxFiles(5)
                    ->helperText('Можна прикріпити до 5 квитанцій')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label('Нотатки')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('debtAccount.member.full_name')
                    ->label('Клієнт')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Замовлення')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Сума')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paymentType.name')
                    ->label('Тип оплати'),

                Tables\Columns\TextColumn::make('cashRegister.name')
                    ->label('Каса'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Дата платежу')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Номер квитанції')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_type_id')
                    ->label('Тип оплати')
                    ->relationship('paymentType', 'name'),
                Tables\Filters\SelectFilter::make('cash_register_id')
                    ->label('Каса')
                    ->relationship('cashRegister', 'name'),
                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('З дати'),
                        Forms\Components\DatePicker::make('until')
                            ->label('По дату'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('payment_date', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('payment_date', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
