<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options(Order::STATUSES)
                    ->required()
                    ->default(Order::STATUSES[Order::STATUS_NEW]),
                Forms\Components\TextInput::make('member.name')
                    ->label('Нікнейм клієнта')
                    ->readOnly(),
                Forms\Components\TextInput::make('source')
                    ->label('Джерело')
                    ->required()
                    ->maxLength(255)
                    ->readOnly()
                    ->default('Пряме замовлення'),
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('total_amount')
                        ->label('Сума')
                        ->readOnly()
                        ->required()
                        ->numeric()
                        ->default(0.00),
                    Forms\Components\TextInput::make('discount_percent')
                        ->label('Знижка %')
                        ->numeric()
                        ->default(0.00),
                    Forms\Components\TextInput::make('discount_amount')
                        ->label('Сума знижки')
                        ->numeric()
                        ->readOnly()
                        ->default(0.00),
                ])
                ->columns(3),
                Forms\Components\TextInput::make('payment_type')
                    ->label('Спосіб оплати')
                    ->maxLength(255),
                FileUpload::make('payment_receipt')
                    ->label('Фото квитанції')
                    ->image()
                    ->directory('receipts')
                    ->imagePreviewHeight('200')
                    ->preserveFilenames()
                    ->maxSize(4096)
                    ->required(false),
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('shipping_phone')
                        ->label('Номер телефону')
                        ->tel()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('shipping_city')
                        ->label('Місто')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('shipping_carrier')
                        ->label('Спосіб доставки')
                        ->maxLength(255)
                        ->default('Нова Пошта'),
                    Forms\Components\TextInput::make('shipping_office')
                        ->label('Відділеня')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('shipping_name')
                        ->label('ПІБ Замовника')
                        ->maxLength(255),
                ])
                    ->columns(2)
                    ->heading('Дані відправки'),
                Forms\Components\Textarea::make('notes')
                    ->label('Нотатки')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Номер замовлення')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.username')
                    ->label('Нікнейм замовника')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('statusName')
                    ->label('Статус')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Сума')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shipping_phone')
                    ->label('Телефон отримувача')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Знижка')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Тип оплати')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('оновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order_number', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('З ...'),
                        DatePicker::make('created_until')->label('По ...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    }),
                SelectFilter::make('status')
                    ->label('Статус замовлення')
                    ->options(Order::STATUSES)
                    ->placeholder('Всі')
                    ->searchable(),
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
