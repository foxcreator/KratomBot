<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashWithdrawalResource\Pages;
use App\Filament\Resources\CashWithdrawalResource\RelationManagers;
use App\Models\CashRegister;
use App\Models\CashWithdrawal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CashWithdrawalResource extends Resource
{
    protected static ?string $model = CashWithdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Гроші';
    protected static ?string $navigationLabel = 'Рух коштів';
    protected static ?string $label = 'Винос';
    protected static ?string $pluralLabel = 'Виноси';
    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('cash_register_id')
                    ->label('Каса')
                    ->options(CashRegister::pluck('name', 'id')->toArray())
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Сума виносу')
                    ->required()
                    ->numeric()
                    ->minValue(0.01),

                Forms\Components\Textarea::make('comment')
                    ->label('Коментар')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cashRegister.name')
                    ->label('Каса')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Сума')
                    ->money('UAH'),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Коментар')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

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
            'index' => Pages\ListCashWithdrawals::route('/'),
            'create' => Pages\CreateCashWithdrawal::route('/create'),
            'edit' => Pages\EditCashWithdrawal::route('/{record}/edit'),
        ];
    }
}
