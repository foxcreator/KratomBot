<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Filament\Resources\PaymentMethodResource\RelationManagers;
use App\Models\PaymentMethod;
use App\Models\CashRegister;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Варіанти оплати';
    protected static ?string $navigationGroup = 'Гроші';
    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Варіант оплати';

    protected static ?string $pluralModelLabel = 'Варіанти оплати';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Назва варіанту оплати')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('cash_register_id')
                    ->label('Каса')
                    ->relationship('cashRegister', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Оберіть касу для цього варіанту оплати. Реквізити будуть братися з каси.'),

                Forms\Components\Textarea::make('details')
                    ->label('Реквізити для оплати')
                    ->rows(4)
                    ->helperText('Реквізити для оплати. Якщо не вказано, будуть використовуватися реквізити з каси.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Активний')
                    ->default(true)
                    ->helperText('Чи доступний цей варіант оплати для вибору в боті'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Назва')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cashRegister.name')
                    ->label('Каса')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Не вказано'),

                Tables\Columns\TextColumn::make('details')
                    ->label('Реквізити')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активні')
                    ->boolean()
                    ->trueLabel('Тільки активні')
                    ->falseLabel('Тільки неактивні')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
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
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
