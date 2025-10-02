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

            // Приховані поля
            Forms\Components\Hidden::make('debt_account_id'),
            Forms\Components\Hidden::make('order_id'),
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
                    ->mutateFormDataUsing(function (array $data): array {
                        $order = $this->getOwnerRecord();
                        $data['debt_account_id'] = $order->debt_account_id;
                        $data['order_id'] = $order->id;
                        return $data;
                    })
                    ->after(function ($record, $data) {
                        Notification::make()
                            ->success()
                            ->title('Платіж додано')
                            ->body('Платіж успішно додано до замовлення')
                            ->send();
                        $this->redirect(request()->header('Referer'));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function () {
                        $this->redirect(request()->header('Referer'));
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        Notification::make()
                            ->success()
                            ->title('Платіж видалено')
                            ->body('Платіж успішно видалено з замовлення')
                            ->send();
                        $this->redirect(request()->header('Referer'));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('payment_date', 'desc');
    }

}
