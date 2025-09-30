<?php

namespace App\Filament\Resources\DebtAccountResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Замовлення';
    protected static ?string $label = 'Замовлення';
    protected static ?string $pluralLabel = 'Замовлення';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('order_number')
                ->label('Номер замовлення')
                ->disabled(),

            Forms\Components\Select::make('status')
                ->label('Статус')
                ->options(\App\Models\Order::STATUSES)
                ->required(),

            Forms\Components\TextInput::make('total_amount')
                ->label('Загальна сума')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\TextInput::make('discount_percent')
                ->label('Знижка (%)')
                ->numeric()
                ->suffix('%')
                ->disabled(),

            Forms\Components\TextInput::make('discount_amount')
                ->label('Сума знижки')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\TextInput::make('final_amount')
                ->label('Фінальна сума')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\TextInput::make('paid_amount')
                ->label('Сплачено')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\TextInput::make('remaining_amount')
                ->label('Залишок')
                ->numeric()
                ->prefix('₴')
                ->disabled(),

            Forms\Components\Select::make('payment_status')
                ->label('Статус оплати')
                ->options(\App\Models\Order::PAYMENT_STATUSES)
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Номер замовлення')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn ($state) => \App\Models\Order::STATUSES[$state] ?? 'Невідомо')
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'pending_payment' => 'danger',
                        'partially_paid' => 'warning',
                        'paid' => 'success',
                        'processing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Загальна сума')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label('До сплати')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Сплачено')
                    ->money('UAH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Залишок')
                    ->money('UAH')
                    ->sortable()
                    ->color(fn ($record) => $record->remaining_amount > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Статус оплати')
                    ->badge()
                    ->formatStateUsing(fn ($state) => \App\Models\Order::PAYMENT_STATUSES[$state] ?? 'Невідомо')
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partial_paid' => 'warning',
                        'paid' => 'success',
                        'overpaid' => 'info',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->infolist([
                        Infolists\Components\Section::make('Основна інформація')
                            ->schema([
                                Infolists\Components\TextEntry::make('order_number')
                                    ->label('Номер замовлення'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Статус')
                                    ->formatStateUsing(fn ($state) => \App\Models\Order::STATUSES[$state] ?? 'Невідомо')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'new' => 'warning',
                                        'pending_payment' => 'danger',
                                        'partially_paid' => 'warning',
                                        'paid' => 'success',
                                        'processing' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger',
                                    }),
                                Infolists\Components\TextEntry::make('member.full_name')
                                    ->label('Клієнт'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Дата створення')
                                    ->dateTime(),
                            ])
                            ->columns(2),

                        Infolists\Components\Section::make('Фінансові показники')
                            ->schema([
                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('Загальна сума')
                                    ->money('UAH'),
                                Infolists\Components\TextEntry::make('discount_percent')
                                    ->label('Знижка (%)')
                                    ->formatStateUsing(fn ($state) => $state ? $state . '%' : '0%')
                                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                                    ->visible(fn ($record) => $record->discount_percent > 0),
                                Infolists\Components\TextEntry::make('discount_amount')
                                    ->label('Сума знижки')
                                    ->money('UAH')
                                    ->color('success')
                                    ->visible(fn ($record) => $record->discount_amount > 0),
                                Infolists\Components\TextEntry::make('final_amount')
                                    ->label('Фінальна сума')
                                    ->money('UAH'),
                                Infolists\Components\TextEntry::make('paid_amount')
                                    ->label('Сплачено')
                                    ->money('UAH'),
                                Infolists\Components\TextEntry::make('remaining_amount')
                                    ->label('Залишок')
                                    ->money('UAH')
                                    ->color(fn ($record) => $record->remaining_amount > 0 ? 'danger' : 'success'),
                                Infolists\Components\TextEntry::make('payment_status')
                                    ->label('Статус оплати')
                                    ->formatStateUsing(fn ($state) => \App\Models\Order::PAYMENT_STATUSES[$state] ?? 'Невідомо')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'unpaid' => 'danger',
                                        'partial_paid' => 'warning',
                                        'paid' => 'success',
                                        'overpaid' => 'info',
                                    }),
                            ])
                            ->columns(2),

                        Infolists\Components\Section::make('Товари в замовленні')
                            ->schema([
                                Infolists\Components\ViewEntry::make('order_items_view')
                                    ->view('filament.orders.items')
                                    ->viewData(function ($record) {
                                        return [
                                            'items' => $record->orderItems()->with(['product', 'productOption'])->get(),
                                            'order' => $record,
                                        ];
                                    }),
                            ])
                            ->columns(1)
                            ->collapsible()
                            ->collapsed(false),
                    ]),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
