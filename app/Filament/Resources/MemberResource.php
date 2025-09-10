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
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Клієнти';
    protected static ?string $label = 'Клієнта';
    protected static ?string $pluralLabel = 'Клієнти';
    protected static ?string $navigationGroup = 'Продажі';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('debtAccount');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('created_at', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Імʼя')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? '✅' : '❌')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('debtAccount.balance')
                    ->label('Баланс')
                    ->formatStateUsing(function ($state) {
                        $balance = $state ?? 0;
                        if ($balance > 0) {
                            return "+" . number_format($balance, 2, ',', ' ') . " ₴";
                        } elseif ($balance < 0) {
                            return number_format($balance, 2, ',', ' ') . " ₴";
                        } else {
                            return "0.00 ₴";
                        }
                    })
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
                Tables\Columns\TextColumn::make('total_orders_amount')
                    ->label('Сума замовлень')
                    ->money('UAH')
                    ->sortable()
                    ->color('info'),
                Tables\Columns\TextColumn::make('total_orders_count')
                    ->label('Кількість замовлень')
                    ->numeric()
                    ->sortable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата реєстрації')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('telegram_status')
                    ->label('Статус Telegram')
                    ->options([
                        'with_telegram' => 'З Telegram',
                        'without_telegram' => 'Без Telegram',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'with_telegram') {
                            return $query->whereNotNull('telegram_id');
                        }
                        if ($data['value'] === 'without_telegram') {
                            return $query->whereNull('telegram_id');
                        }
                        return $query;
                    }),
                SelectFilter::make('balance_status')
                    ->label('Статус балансу')
                    ->options([
                        'positive' => 'Позитивний баланс',
                        'negative' => 'Негативний баланс',
                        'zero' => 'Нульовий баланс',
                    ])
                    ->query(function ($query, array $data) {
                        $query->whereHas('debtAccount');
                        
                        if ($data['value'] === 'positive') {
                            return $query->whereHas('debtAccount', function ($q) {
                                $q->where('balance', '>', 0);
                            });
                        }
                        if ($data['value'] === 'negative') {
                            return $query->whereHas('debtAccount', function ($q) {
                                $q->where('balance', '<', 0);
                            });
                        }
                        if ($data['value'] === 'zero') {
                            return $query->whereHas('debtAccount', function ($q) {
                                $q->where('balance', '=', 0);
                            });
                        }
                        return $query;
                    })
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
