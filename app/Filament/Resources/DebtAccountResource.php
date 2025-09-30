<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DebtAccountResource\Pages;
use App\Filament\Resources\DebtAccountResource\RelationManagers;
use App\Models\DebtAccount;
use App\Models\Member;
use App\Models\PaymentType;
use App\Models\CashRegister;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DebtAccountResource extends Resource
{
    protected static ?string $model = DebtAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Заборгованість';
    protected static ?string $label = 'Рахунок заборгованості';
    protected static ?string $pluralLabel = 'Заборгованість';
    protected static ?string $navigationGroup = 'Продажі';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->where('remaining_debt', '>', 0)
                      ->orWhere('balance', '!=', 0);
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('member_id')
                    ->label('Клієнт')
                    ->relationship('member', 'full_name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('full_name')
                            ->label('Ім\'я')
                            ->required(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->required()
                            ->unique('members', 'phone'),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique('members', 'email')
                            ->nullable(),
                    ]),

                Forms\Components\TextInput::make('total_debt')
                    ->label('Загальна сума боргу')
                    ->numeric()
                    ->prefix('₴')
                    ->disabled(true)
                    ->helperText('Розраховується автоматично з замовлень'),

                Forms\Components\TextInput::make('paid_amount')
                    ->label('Сплачена сума')
                    ->numeric()
                    ->prefix('₴')
                    ->disabled(true)
                    ->helperText('Розраховується автоматично з платежів'),

                Forms\Components\TextInput::make('remaining_debt')
                    ->label('Залишок боргу')
                    ->numeric()
                    ->prefix('₴')
                    ->disabled(true)
                    ->helperText('Розраховується автоматично'),

                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options(DebtAccount::STATUSES)
                    ->default(DebtAccount::STATUS_ACTIVE)
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Нотатки')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Інформація про заборгованість')
                    ->schema([
                        Infolists\Components\TextEntry::make('member.full_name')
                            ->label('Клієнт'),
                        Infolists\Components\TextEntry::make('member.phone')
                            ->label('Телефон'),
                        Infolists\Components\TextEntry::make('total_debt')
                            ->label('Загальний борг')
                            ->money('UAH'),
                        Infolists\Components\TextEntry::make('paid_amount')
                            ->label('Сплачено')
                            ->money('UAH'),
                        Infolists\Components\TextEntry::make('remaining_debt')
                            ->label('Залишок')
                            ->money('UAH')
                            ->color(fn ($record) => $record->remaining_debt > 0 ? 'danger' : 'success'),
                        Infolists\Components\TextEntry::make('statusName')
                            ->label('Статус')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Активний' => 'warning',
                                'Закритий' => 'success',
                                'Прострочений' => 'danger',
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('updated_at', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Клієнт')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('member.phone')
                    ->label('Телефон')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_debt')
                    ->label('Загальний борг')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Сплачено')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_debt')
                    ->label('Залишок')
                    ->money('UAH')
                    ->sortable()
                    ->color(fn ($record) => $record->remaining_debt > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Баланс клієнта')
                    ->money('UAH')
                    ->sortable()
                    ->color(fn ($record) => $record->balance > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('statusName')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Активний' => 'warning',
                        'Закритий' => 'success',
                        'Прострочений' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('last_payment_date')
                    ->label('Останній платіж')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Останнє оновлення')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(DebtAccount::STATUSES),
                
                Tables\Filters\TernaryFilter::make('has_debt')
                    ->label('Показати тільки з заборгованістю')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function ($q) {
                            $q->where('remaining_debt', '>', 0)
                              ->orWhere('balance', '!=', 0);
                        }),
                        false: fn (Builder $query) => $query->where('remaining_debt', '=', 0)
                                                           ->where('balance', '=', 0),
                    )
                    ->default(true),
            ])
            ->actions([
                Action::make('addPayment')
                    ->label('Додати платіж')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Сума платежу')
                            ->numeric()
                            ->prefix('₴')
                            ->required(),
                        Forms\Components\Select::make('payment_type_id')
                            ->label('Тип оплати')
                            ->options(PaymentType::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\Select::make('cash_register_id')
                            ->label('Каса')
                            ->options(CashRegister::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Нотатки'),
                    ])
                    ->action(function (array $data, DebtAccount $record) {
                        $record->addPayment(
                            $data['amount'],
                            $data['payment_type_id'],
                            $data['cash_register_id'],
                            null,
                            $data['notes'] ?? null
                        );

                        Notification::make()
                            ->success()
                            ->title('Платіж додано')
                            ->body('Платіж успішно додано до рахунку заборгованості')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('remaining_debt', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDebtAccounts::route('/'),
            // 'create' => Pages\CreateDebtAccount::route('/create'), // Прибрано - створюється автоматично
            'edit' => Pages\EditDebtAccount::route('/{record}/edit'),
        ];
    }
}
