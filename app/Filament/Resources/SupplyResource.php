<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplyResource\Pages;
use App\Filament\Resources\SupplyResource\RelationManagers;
use App\Filament\Resources\SupplyResource\RelationManagers\SupplyItemsRelationManager;
use App\Models\Supply;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplyResource extends Resource
{
    protected static ?string $model = Supply::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Поставки';
    protected static ?string $pluralLabel = 'Поставки';
    protected static ?string $modelLabel = 'Поставка';
    protected static ?string $navigationGroup = 'Склад';
    protected static ?int $navigationSort = 13;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Дані поставки')
                ->schema([
                    Forms\Components\TextInput::make('number')
                        ->label('Номер поставки')
                        ->disabled()
                        ->dehydrated(false)
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('date')
                        ->label('Дата')
                        ->required(),
                    Forms\Components\TextInput::make('supplier_name')
                        ->label('Постачальник')
                        ->maxLength(255),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('Номер'),
            Tables\Columns\TextColumn::make('date')->label('Дата')->date(),
            Tables\Columns\TextColumn::make('supplier_name')->label('Постачальник'),
            Tables\Columns\TextColumn::make('items_count')->label('К-сть позицій')->counts('supplyItems'),
            Tables\Columns\TextColumn::make('created_at')->label('Створено')->since(),
        ])->filters([]);
    }

    public static function getRelations(): array
    {
        return [
            SupplyItemsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplies::route('/'),
            'create' => Pages\CreateSupply::route('/create'),
            'edit' => Pages\EditSupply::route('/{record}/edit'),
        ];
    }
}
