<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Brand;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Товари';
    protected static ?string $navigationGroup = 'Склад';
    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Назва')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('Опис')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price')
                    ->label('Ціна')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('brand_id')
                    ->label('Категорія')
                    ->required()
                    ->searchable()
                    ->options(Brand::pluck('name', 'id')),
                Forms\Components\Select::make('subcategory_id')
                    ->label('Підкатегорія')
                    ->options(\App\Models\Subcategory::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Toggle::make('is_top_sales')
                    ->label('Топ продажів')
                    ->required(),
                Forms\Components\FileUpload::make('image_url')
                    ->label('Зображення')
                    ->imageEditor()
                    ->image(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Фото')
                    ->disk('public')
                    ->height(60)
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Назва')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Ціна')
                    ->searchable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Категорія')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subcategory.name')
                    ->label('Підкатегорія')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_top_sales')
                    ->label('Топ продажів')
                    ->boolean(),
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
            RelationManagers\ProductsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
