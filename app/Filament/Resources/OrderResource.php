<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Замовлення';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order_number')
                    ->label('Номер Замовлення')
                    ->maxLength(255),
                Forms\Components\TextInput::make('member_id')
                    ->label('Клієнт')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('new'),
                Forms\Components\TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('shipping_phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('shipping_city')
                    ->maxLength(255),
                Forms\Components\TextInput::make('shipping_carrier')
                    ->maxLength(255),
                Forms\Components\TextInput::make('shipping_office')
                    ->maxLength(255),
                Forms\Components\TextInput::make('shipping_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('discount_percent')
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('discount_amount')
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('source')
                    ->required()
                    ->maxLength(255)
                    ->default('direct'),
                Forms\Components\TextInput::make('payment_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('payment_receipt')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shipping_phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
