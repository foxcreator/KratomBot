<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Resources\MemberResource\RelationManagers\OrdersRelationManager;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Клієнти';
    protected static ?string $label = 'Клієнта';
    protected static ?string $pluralLabel = 'Клієнти';
    protected static ?string $navigationGroup = 'Продажі';
    protected static ?int $navigationSort = 2;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('telegram_id')
                    ->tel()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('username')
                    ->maxLength(255),
                TextInput::make('full_name')
                    ->label('Імʼя')
                    ->required(),

                TextInput::make('phone')
                    ->label('Телефон')
                    ->tel()
                    ->required()
                    ->unique('members', 'phone'),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->unique('members', 'email')
                    ->nullable(),

                TextInput::make('address')
                    ->label('Адреса')
                    ->nullable(),

                TextInput::make('city')
                    ->label('Місто')
                    ->nullable(),

                TextInput::make('shipping_office')
                    ->label('Відділення Нової пошти')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('telegram_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
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
            OrdersRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
//            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
