<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockResource\Pages;
use App\Filament\Resources\StockResource\RelationManagers;
use App\Models\ProductOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StockResource extends Resource
{
    protected static ?string $model = ProductOption::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Залишки';
    protected static ?string $pluralLabel = 'Залишки';
    protected static ?string $navigationGroup = 'Склад';

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form; // Без форми
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('created_at', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable(), // дозволяє сортування через Eager Load
                Tables\Columns\TextColumn::make('name')->label('Варіант')->sortable(),
                Tables\Columns\TextColumn::make('current_quantity')->label('Залишок')->sortable(),
                Tables\Columns\TextColumn::make('opt_price')->label('Закупка')->money('UAH'),
                Tables\Columns\TextColumn::make('wholesale_price')->label('Опт')->money('UAH'),
                Tables\Columns\TextColumn::make('price')->label('Роздріб')->money('UAH'),
                Tables\Columns\IconColumn::make('in_stock')->label('В наявності')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('in_stock')
                    ->label('В наявності')
                    ->placeholder('Усі')
                    ->trueLabel('Є в наявності')
                    ->falseLabel('Немає в наявності'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStocks::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
