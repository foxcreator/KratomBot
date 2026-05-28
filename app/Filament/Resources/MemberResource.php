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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Settings\TelegramSettings;

class MemberResource extends Resource
{
    private const ALLOWED_DELETE_EMAIL = 'foxcreatorg@gmail.com';

    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Клієнти';
    protected static ?string $label = 'Клієнта';
    protected static ?string $pluralLabel = 'Клієнти';
    protected static ?string $navigationGroup = 'Продажі';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return app(TelegramSettings::class)->show_sales_group;
    }

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
                Tables\Columns\TextColumn::make('channel_join_source')
                    ->label('Джерело каналу')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Member::CHANNEL_JOIN_SOURCE_BOT => 'Через бота',
                        Member::CHANNEL_JOIN_SOURCE_ORGANIC => 'Органічно',
                        Member::CHANNEL_JOIN_SOURCE_UNKNOWN => 'Невідомо',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        Member::CHANNEL_JOIN_SOURCE_BOT => 'success',
                        Member::CHANNEL_JOIN_SOURCE_ORGANIC => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('channel_joined_at')
                    ->label('Підписка на канал')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('is_subscribed')
                    ->label('На каналі зараз')
                    ->formatStateUsing(function ($state, Member $record) {
                        if (!$record->telegram_id) {
                            return '—';
                        }
                        if ($state === null) {
                            return 'н/д';
                        }
                        return ((bool) $state) ? 'Так' : 'Ні';
                    })
                    ->badge()
                    ->color(function (Member $record): string {
                        if (!$record->telegram_id || $record->is_subscribed === null) {
                            return 'gray';
                        }
                        return ((bool) $record->is_subscribed) ? 'success' : 'danger';
                    }),
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
                SelectFilter::make('channel_join_source')
                    ->label('Джерело підписки на канал')
                    ->options([
                        Member::CHANNEL_JOIN_SOURCE_BOT => 'Через бота',
                        Member::CHANNEL_JOIN_SOURCE_ORGANIC => 'Органічно',
                        Member::CHANNEL_JOIN_SOURCE_UNKNOWN => 'Невідомо',
                    ]),
                SelectFilter::make('is_subscribed')
                    ->label('Підписка на канал')
                    ->options([
                        '1' => 'Підписані',
                        '0' => 'Не підписані',
                    ])
                    ->query(function ($query, array $data) {
                        if (($data['value'] ?? null) === '1') {
                            return $query->where('is_subscribed', true);
                        }
                        if (($data['value'] ?? null) === '0') {
                            return $query->where(function ($q) {
                                $q->where('is_subscribed', false)->orWhereNull('is_subscribed');
                            });
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
                        $value = $data['value'] ?? null;
                        if (!$value) {
                            return $query;
                        }

                        if ($value === 'positive') {
                            return $query->whereHas('debtAccount', fn ($q) => $q->where('balance', '>', 0));
                        }
                        if ($value === 'negative') {
                            return $query->whereHas('debtAccount', fn ($q) => $q->where('balance', '<', 0));
                        }
                        if ($value === 'zero') {
                            return $query->whereHas('debtAccount', fn ($q) => $q->where('balance', '=', 0));
                        }

                        return $query;
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => static::canManageMemberDeletion()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => static::canManageMemberDeletion()),
                ]),
            ]);
    }

    public static function canDeleteAny(): bool
    {
        return static::canManageMemberDeletion();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageMemberDeletion();
    }

    protected static function canManageMemberDeletion(): bool
    {
        $user = Auth::user();

        return $user !== null && strtolower((string) $user->email) === self::ALLOWED_DELETE_EMAIL;
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
