<?php

namespace App\Filament\Resources\DebtAccountResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                    ->label('Додати платіж'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
