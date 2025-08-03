<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Назва варіанту')
                ->required(),
            Forms\Components\TextInput::make('price')
                ->label('Ціна')
                ->required()
                ->numeric(),
            Forms\Components\TextInput::make('current_quantity')
                ->label('Кількість')
                ->numeric()
                ->default(0)
                ->disabled()
                ->helperText('Кількість визначається автоматично при поставках та продажах'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Назва'),
                Tables\Columns\TextColumn::make('price')->label('Ціна')->money('UAH'),
                Tables\Columns\TextColumn::make('current_quantity')->label('К-сть'),
                Tables\Columns\IconColumn::make('in_stock')->label('В наявності')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
