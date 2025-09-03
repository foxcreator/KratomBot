<?php

namespace App\Filament\Resources\DebtAccountResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Замовлення';
    protected static ?string $label = 'Замовлення';
    protected static ?string $pluralLabel = 'Замовлення';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('order_number')
                ->label('Номер замовлення')
                ->disabled(),

            Forms\Components\Select::make('status')
                ->label('Статус')
                ->options(\App\Models\Order::STATUSES)
                ->required(),

            Forms\Components\TextInput::make('total_amount')
                ->label('Загальна сума')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\TextInput::make('final_amount')
                ->label('Фінальна сума')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\TextInput::make('paid_amount')
                ->label('Сплачено')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\TextInput::make('remaining_amount')
                ->label('Залишок')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\Select::make('payment_status')
                ->label('Статус оплати')
                ->options(\App\Models\Order::PAYMENT_STATUSES)
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Номер замовлення')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('statusName')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Нове' => 'warning',
                        'Оплачено' => 'success',
                        'Виконано' => 'info',
                        'Скасовано' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Загальна сума')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label('До сплати')
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

                Tables\Columns\TextColumn::make('paymentStatusName')
                    ->label('Статус оплати')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Не оплачено' => 'danger',
                        'Частково оплачено' => 'warning',
                        'Оплачено' => 'success',
                        'Переплачено' => 'info',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
