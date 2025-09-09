<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\PaymentType;
use App\Models\CashRegister;
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
                ->required(),

            Forms\Components\Select::make('payment_type_id')
                ->label('Тип оплати')
                ->options(PaymentType::pluck('name', 'id'))
                ->required(),

            Forms\Components\Select::make('cash_register_id')
                ->label('Каса')
                ->options(CashRegister::pluck('name', 'id'))
                ->required(),

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
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Додати платіж')
                    ->after(function ($record, $data) {
                        $order = $this->getOwnerRecord();
                        
                        // Оновлюємо суми замовлення
                        $order->increment('paid_amount', $data['amount']);
                        $order->decrement('remaining_amount', $data['amount']);
                        
                        // Оновлюємо статус оплати
                        if ($order->remaining_amount <= 0) {
                            $order->update([
                                'payment_status' => 'paid',
                                'status' => 'paid'
                            ]);
                        } else {
                            $order->update([
                                'payment_status' => 'partial_paid',
                                'status' => 'partially_paid'
                            ]);
                        }

                        // Оновлюємо debt account
                        $this->updateDebtAccount($order, $data['amount']);

                        Notification::make()
                            ->success()
                            ->title('Платіж додано')
                            ->body('Платіж успішно додано до замовлення')
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        $order = $this->getOwnerRecord();
                        
                        // Відновлюємо суми замовлення
                        $order->decrement('paid_amount', $record->amount);
                        $order->increment('remaining_amount', $record->amount);
                        
                        // Оновлюємо статус оплати
                        if ($order->paid_amount <= 0) {
                            $order->update([
                                'payment_status' => 'unpaid',
                                'status' => 'pending_payment'
                            ]);
                        } else {
                            $order->update([
                                'payment_status' => 'partial_paid',
                                'status' => 'partially_paid'
                            ]);
                        }

                        // Відновлюємо debt account
                        $this->updateDebtAccount($order, -$record->amount);

                        Notification::make()
                            ->success()
                            ->title('Платіж видалено')
                            ->body('Платіж успішно видалено з замовлення')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    private function updateDebtAccount($order, $amount)
    {
        $member = $order->member;
        $debtAccount = $member->debtAccount;
        
        if (!$debtAccount) {
            // Створюємо debt account на основі замовлення
            $debtAccount = \App\Models\DebtAccount::create([
                'member_id' => $member->id,
                'total_debt' => $order->total_amount,
                'paid_amount' => 0,
                'remaining_debt' => $order->total_amount,
                'balance' => 0,
                'status' => 'active',
            ]);
        }

        // Оновлюємо суми
        $debtAccount->increment('paid_amount', $amount);
        $debtAccount->decrement('remaining_debt', $amount);
        
        // Розраховуємо новий баланс (може бути позитивним при переплаті)
        $newBalance = $debtAccount->paid_amount - $debtAccount->total_debt;
        $debtAccount->update(['balance' => $newBalance]);
        
        // Оновлюємо статус
        if ($debtAccount->remaining_debt <= 0) {
            $debtAccount->update(['status' => 'closed']);
            
            // Якщо є переплата - зачисляємо її на баланс клієнта
            if ($newBalance > 0) {
                $debtAccount->increment('balance', $newBalance);
            }
        } else {
            $debtAccount->update(['status' => 'active']);
        }
    }
}
