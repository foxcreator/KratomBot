<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\ProductOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';
    protected static ?string $recordTitleAttribute = 'id';


    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Товар')
                ->relationship('product', 'name')
                ->required()
                ->reactive(),

            Forms\Components\Select::make('product_option_id')
                ->label('Варіант товару')
                ->options(fn () => ProductOption::all()->pluck('name', 'id')) // або обмежити фільтром по product_id
                ->searchable()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $option = ProductOption::find($state);
                    if ($option) {
                        $set('price', $option->price);
                    } else {
                        $set('price', null);
                    }
                }),

            Forms\Components\TextInput::make('quantity')
                ->label('Кількість')
                ->numeric()
                ->default(1),

            Forms\Components\TextInput::make('price')
                ->label('Ціна за одиницю')
                ->numeric(),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Товар'),
                Tables\Columns\TextColumn::make('productOption.name')->label('Опція'),
                Tables\Columns\TextColumn::make('quantity')->label('Кількість'),
                Tables\Columns\TextColumn::make('price')->money('UAH')->label('Ціна'),
                Tables\Columns\TextColumn::make('total')->money('UAH')->label('Сума')->getStateUsing(
                    fn($record) => $record->quantity * $record->price
                ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
