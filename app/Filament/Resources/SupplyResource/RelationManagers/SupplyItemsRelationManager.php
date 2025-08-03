<?php

namespace App\Filament\Resources\SupplyResource\RelationManagers;

use App\Models\Product;
use App\Models\ProductOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SupplyItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'supplyItems';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Товар')
                ->options(Product::all()->pluck('name', 'id'))
                ->searchable()
                ->reactive()
                ->required(),

            Forms\Components\Select::make('product_option_id')
                ->label('Варіант товару')
                ->options(function (callable $get) {
                    $productId = $get('product_id');
                    if (!$productId) return [];

                    return ProductOption::where('product_id', $productId)
                        ->get()
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->required()
                ->reactive(),

            Forms\Components\TextInput::make('quantity')
                ->label('Кількість')
                ->numeric()
                ->minValue(1)
                ->required(),

            Forms\Components\TextInput::make('purchase_price')
                ->label('Ціна закупки')
                ->numeric()
                ->required()
                ->afterStateUpdated(function ($state, callable $set) {
                    $lastSupply = \App\Models\SupplyItem::where('product_option_id', $state)
                        ->latest()
                        ->first();

                    if ($lastSupply) {
                        $set('purchase_price', $lastSupply->purchase_price);
                    }
                }),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('productOption.product.name')->label('Товар'),
            Tables\Columns\TextColumn::make('productOption.name')->label('Варіант'),
            Tables\Columns\TextColumn::make('quantity')->label('Кількість'),
            Tables\Columns\TextColumn::make('purchase_price')->label('Ціна')->money('UAH'),
            Tables\Columns\TextColumn::make('total')
                ->label('Сума')
                ->money('UAH')
                ->getStateUsing(fn($record) => $record->quantity * $record->purchase_price),
        ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record, $data) {
                        $this->syncStock($record->productOption, $data['quantity']);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record, $data) {
                        $delta = $data['quantity'] - $record->getOriginal('quantity');
                        $this->syncStock($record->productOption, $delta);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        $this->syncStock($record->productOption, -$record->quantity);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->after(function ($records) {
                        foreach ($records as $record) {
                            $this->syncStock($record->productOption, -$record->quantity);
                        }
                    }),
            ]);
    }

    protected function syncStock(ProductOption $option, int $delta): void
    {
        $option->increment('current_quantity', $delta);
        $option->update([
            'in_stock' => $option->current_quantity > 0,
        ]);
    }
}
