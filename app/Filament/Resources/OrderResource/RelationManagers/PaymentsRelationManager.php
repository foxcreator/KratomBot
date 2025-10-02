<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\PaymentType;
use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Order;
use Filament\Notifications\Notification;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Платежі';
    protected static ?string $label = 'Платіж';
    protected static ?string $pluralLabel = 'Платежі';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('amount')
                ->label('Сума платежу')
                ->numeric()
                ->prefix('₴')
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, $get) {
                    // Перевіряємо баланс при зміні суми
                    $this->validateBalance($state ? (float) $state : null, $get('payment_method'), $set);
                }),

            Forms\Components\Select::make('payment_method')
                ->label('Метод оплати')
                ->options(Payment::PAYMENT_METHODS)
                ->default(Payment::PAYMENT_METHOD_CASH)
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, $get) {
                    // Перевіряємо баланс при зміні методу оплати
                    $amount = $get('amount');
                    $this->validateBalance($amount ? (float) $amount : null, $state, $set);
                    
                    // Очищаємо поля при виборі списання з балансу
                    if ($state === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                        $set('payment_type_id', null);
                        $set('cash_register_id', null);
                    }
                }),

            Forms\Components\Select::make('payment_type_id')
                ->label('Тип оплати')
                ->options(PaymentType::pluck('name', 'id'))
                ->required(fn ($get) => $get('payment_method') !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION)
                ->disabled(fn ($get) => $get('payment_method') === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION)
                ->helperText(fn ($get) => $get('payment_method') === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION 
                    ? 'Не потрібно при списанні з балансу' 
                    : 'Оберіть тип оплати'),

            Forms\Components\Select::make('cash_register_id')
                ->label('Каса')
                ->options(CashRegister::pluck('name', 'id'))
                ->required(fn ($get) => $get('payment_method') !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION)
                ->disabled(fn ($get) => $get('payment_method') === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION)
                ->helperText(fn ($get) => $get('payment_method') === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION 
                    ? 'Не потрібно при списанні з балансу' 
                    : 'Оберіть касу'),

            Forms\Components\DatePicker::make('payment_date')
                ->label('Дата платежу')
                ->default(now())
                ->required(),

            Forms\Components\TextInput::make('receipt_number')
                ->label('Номер квитанції')
                ->disabled(),

            Forms\Components\Textarea::make('notes')
                ->label('Нотатки')
                ->columnSpanFull(),

            // Інформаційний блок з балансом
            Forms\Components\Placeholder::make('balance_info')
                ->label('Баланс клієнта')
                ->content(function () {
                    $order = $this->getOwnerRecord();
                    $balance = $order->debtAccount?->balance ?? 0;
                    return number_format($balance, 2) . ' ₴';
                })
                ->visible(fn ($get) => $get('payment_method') === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION),


            // Поле для відображення помилки балансу
            Forms\Components\TextInput::make('balance_error')
                ->label('')
                ->disabled()
                ->visible(fn ($get) => !empty($get('balance_error')))
                ->extraAttributes(['class' => 'text-red-600 font-medium'])
                ->formatStateUsing(fn ($get) => $get('balance_error')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(function (): string {
                $order = $this->getOwnerRecord();
                
                // Оновлюємо фінансові показники перед перевіркою
                $order->updateOrderFinancials();
                
                if ($order->remaining_amount <= 0) {
                    if ($order->remaining_amount < 0) {
                        return 'Платежі (замовлення переплачене - редагування заблоковано)';
                    }
                    return 'Платежі (замовлення повністю оплачене - редагування заблоковано)';
                }
                return 'Платежі';
            })
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label('Сума')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Метод оплати')
                    ->formatStateUsing(fn (string $state): string => Payment::PAYMENT_METHODS[$state] ?? $state),

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

                Tables\Columns\TextColumn::make('notes')
                    ->label('Коментар')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Додати платіж')
                    ->visible(fn (): bool => $this->canModifyPayments())
                    ->disabled(fn (): bool => !$this->canModifyPayments())
                    ->mutateFormDataUsing(function (array $data): array {
                        $order = $this->getOwnerRecord();
                        $data['debt_account_id'] = $order->debt_account_id;
                        $data['order_id'] = $order->id;
                        return $data;
                    })
                    ->before(function (array $data): void {
                        // Перевіряємо достатність балансу перед створенням платежу
                        if (isset($data['payment_method']) && 
                            $data['payment_method'] === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                            
                            if (!$this->hasSufficientBalance($data['amount'])) {
                                $order = $this->getOwnerRecord();
                                $balance = $order->debtAccount?->balance ?? 0;
                                
                                Notification::make()
                                    ->danger()
                                    ->title('Недостатньо коштів на балансі')
                                    ->body("На балансі доступно: " . number_format($balance, 2) . " ₴")
                                    ->send();
                                
                                $this->halt();
                            }
                        }
                    })
                    ->after(function ($record) {
                        $order = $this->getOwnerRecord();
                        
                        // Оновлюємо фінансові показники замовлення
                        $order->updateOrderFinancials();
                        
                        // Якщо платіж з балансу - списуємо з балансу
                        if ($record->payment_method === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                            $order->debtAccount?->decrement('balance', $record->amount);
                            // Для списання з балансу оновлюємо тільки статус замовлення без DebtAccount
                            $this->updateOrderStatusOnly($order);
                        } else {
                            // Для зовнішніх платежів оновлюємо все
                            $order->updateDebtAccountTotals();
                            $order->updateStatusBasedOnPayments();
                        }
                        
                        // Перезавантажуємо сторінку, щоб оновити форму
                        $this->redirect(request()->header('Referer'));
                        
                        $methodText = $record->payment_method === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION 
                            ? 'з балансу клієнта' 
                            : 'до замовлення';
                            
                        Notification::make()
                            ->success()
                            ->title('Платіж додано')
                            ->body("Платіж успішно додано {$methodText}")
                            ->send();
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record): bool => $this->canModifyPayments())
                    ->disabled(fn ($record): bool => !$this->canModifyPayments())
                    ->before(function (array $data, $record): void {
                        // Перевіряємо достатність балансу при зміні на списання з балансу
                        if (isset($data['payment_method']) && 
                            $data['payment_method'] === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                            
                            if (!$this->hasSufficientBalance($data['amount'])) {
                                $order = $this->getOwnerRecord();
                                $balance = $order->debtAccount?->balance ?? 0;
                                
                                Notification::make()
                                    ->danger()
                                    ->title('Недостатньо коштів на балансі')
                                    ->body("На балансі доступно: " . number_format($balance, 2) . " ₴")
                                    ->send();
                                
                                $this->halt();
                            }
                        }
                    })
                    ->after(function ($record) {
                        $order = $this->getOwnerRecord();
                        
                        // Оновлюємо фінансові показники замовлення
                        $order->updateOrderFinancials();
                        
                        // Обробляємо зміни в методі оплати
                        $originalMethod = $record->getOriginal('payment_method');
                        $newMethod = $record->payment_method;
                        
                        // Якщо змінили з готівки на баланс - списуємо з балансу
                        if ($originalMethod !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION && 
                            $newMethod === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                            $order->debtAccount?->decrement('balance', $record->amount);
                        }
                        // Якщо змінили з балансу на готівку - повертаємо на баланс
                        elseif ($originalMethod === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION && 
                                $newMethod !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                            $order->debtAccount?->increment('balance', $record->amount);
                            // Для зовнішніх платежів оновлюємо DebtAccount totals
                            $order->updateDebtAccountTotals();
                        }
                        
                        $order->updateStatusBasedOnPayments();
                        
                        // Перезавантажуємо сторінку, щоб оновити форму
                        $this->redirect(request()->header('Referer'));
                        
                        Notification::make()
                            ->success()
                            ->title('Платіж оновлено')
                            ->body('Платіж успішно оновлено')
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record): bool => $this->canModifyPayments())
                    ->disabled(fn ($record): bool => !$this->canModifyPayments())
                    ->before(function ($record) {
                        $order = $this->getOwnerRecord();
                        
                        // Якщо видаляємо платіж з балансу - повертаємо кошти на баланс
                        if ($record->payment_method === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                            $order->debtAccount?->increment('balance', $record->amount);
                        }
                    })
                    ->after(function ($record) {
                        $order = $this->getOwnerRecord();
                        
                        // Оновлюємо фінансові показники замовлення
                        $order->updateOrderFinancials();
                        
                        // Для зовнішніх платежів оновлюємо DebtAccount totals
                        if ($record->payment_method !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION) {
                            $order->updateDebtAccountTotals();
                        }
                        
                        $order->updateStatusBasedOnPayments();
                        
                        // Перезавантажуємо сторінку, щоб оновити форму
                        $this->redirect(request()->header('Referer'));
                        
                        $methodText = $record->payment_method === Payment::PAYMENT_METHOD_BALANCE_DEDUCTION 
                            ? 'з балансу клієнта' 
                            : 'з замовлення';
                            
                        Notification::make()
                            ->success()
                            ->title('Платіж видалено')
                            ->body("Платіж успішно видалено {$methodText}")
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => $this->canModifyPayments())
                    ->disabled(fn (): bool => !$this->canModifyPayments()),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    /**
     * Перевіряє, чи можна модифікувати платежі для поточного замовлення
     */
    private function canModifyPayments(): bool
    {
        $order = $this->getOwnerRecord();
        
        // Оновлюємо фінансові показники перед перевіркою
        $order->updateOrderFinancials();
        
        // Якщо замовлення повністю оплачене або переплачене (remaining_amount <= 0), забороняємо модифікацію
        return $order->remaining_amount > 0;
    }

    /**
     * Валідує баланс при списанні
     */
    private function validateBalance(?float $amount, ?string $paymentMethod, callable $set): void
    {
        if ($paymentMethod !== Payment::PAYMENT_METHOD_BALANCE_DEDUCTION || !$amount) {
            $set('balance_error', null);
            return;
        }

        $order = $this->getOwnerRecord();
        $balance = $order->debtAccount?->balance ?? 0;

        if ($amount > $balance) {
            $set('balance_error', "Недостатньо коштів на балансі. Доступно: " . number_format($balance, 2) . " ₴");
        } else {
            $set('balance_error', null);
        }
    }

    /**
     * Перевіряє достатність балансу для списання
     */
    private function hasSufficientBalance(float $amount): bool
    {
        $order = $this->getOwnerRecord();
        $balance = $order->debtAccount?->balance ?? 0;
        return $amount <= $balance;
    }

    /**
     * Оновлює тільки статус замовлення без оновлення DebtAccount
     */
    private function updateOrderStatusOnly(Order $order): void
    {
        if ($order->remaining_amount <= 0) {
            $order->updateQuietly([
                'status' => Order::STATUS_PAID,
                'payment_status' => Order::PAYMENT_STATUS_PAID
            ]);
        } elseif ($order->paid_amount > 0) {
            $order->updateQuietly([
                'status' => Order::STATUS_PARTIALLY_PAID,
                'payment_status' => Order::PAYMENT_STATUS_PARTIAL_PAID
            ]);
        } else {
            $order->updateQuietly([
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => Order::PAYMENT_STATUS_UNPAID
            ]);
        }
    }

}
