<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Замовлення';
    protected static ?string $recordTitleAttribute = 'id';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->heading('🛒 Замовлення клієнта')
            ->description('Перегляд всіх замовлень обраного клієнта')
            ->columns([
                TextColumn::make('order_number')
                    ->label('№ Замовлення')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                    
                TextColumn::make('statusName')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Нове' => 'warning',
                        'Очікує оплати' => 'danger',
                        'Частково оплачено' => 'warning',
                        'Оплачено' => 'success',
                        'Обробляється' => 'info',
                        'Виконано' => 'success',
                        'Скасовано' => 'danger',
                    })
                    ->sortable(),
                    
                TextColumn::make('total_amount')
                    ->label('Загальна сума')
                    ->money('UAH')
                    ->sortable()
                    ->weight('bold')
                    ->color('info'),
                    
                TextColumn::make('paid_amount')
                    ->label('Сплачено')
                    ->money('UAH')
                    ->color('success')
                    ->sortable(),
                    
                TextColumn::make('remaining_amount')
                    ->label('Залишок')
                    ->money('UAH')
                    ->color(fn ($record) => $record->remaining_amount > 0 ? 'danger' : 'success')
                    ->sortable(),
                    
                TextColumn::make('paymentStatusName')
                    ->label('Оплата')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Не оплачено' => 'danger',
                        'Частково оплачено' => 'warning',
                        'Оплачено' => 'success',
                        'Переплачено' => 'info',
                    }),
                    
                TextColumn::make('orderItems')
                    ->label('Товари')
                    ->formatStateUsing(function ($record) {
                        $count = $record->orderItems->count();
                        $totalQty = $record->orderItems->sum('quantity');
                        return "{$count} позицій ({$totalQty} шт.)";
                    })
                    ->color('gray'),
                    
                TextColumn::make('created_at')
                    ->label('Дата створення')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('💳 Статус оплати')
                    ->options([
                        'unpaid' => '🔴 Не оплачено',
                        'partial_paid' => '🟡 Частково оплачено',
                        'paid' => '🟢 Оплачено',
                        'overpaid' => '🔵 Переплачено',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('📋 Статус замовлення')
                    ->options([
                        'new' => '🆕 Нове',
                        'pending_payment' => '⏳ Очікує оплати',
                        'partially_paid' => '🟡 Частково оплачено',
                        'paid' => '✅ Оплачено',
                        'processing' => '⚙️ Обробляється',
                        'completed' => '🎉 Завершено',
                        'cancelled' => '❌ Скасовано',
                    ]),
                Tables\Filters\Filter::make('has_items')
                    ->label('🛒 З товарами')
                    ->query(fn ($query) => $query->whereHas('orderItems'))
                    ->toggle(),
                Tables\Filters\Filter::make('high_value')
                    ->label('💰 Високі суми (>10,000 грн)')
                    ->query(fn ($query) => $query->where('total_amount', '>', 10000))
                    ->toggle(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->infolist([
                        // 📋 ОСНОВНА ІНФОРМАЦІЯ
                        Group::make([
                            TextEntry::make('order_number')
                                ->label('Номер Замовлення')
                                ->color('primary')
                                ->weight('bold')
                                ->size('lg'),
                            TextEntry::make('statusName')
                                ->label('Статус')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Нове' => 'warning',
                                    'Очікує оплати' => 'danger',
                                    'Частково оплачено' => 'warning',
                                    'Оплачено' => 'success',
                                    'Обробляється' => 'info',
                                    'Виконано' => 'success',
                                    'Скасовано' => 'danger',
                                }),
                            TextEntry::make('created_at')
                                ->label('Дата створення')
                                ->dateTime('d.m.Y H:i')
                                ->color('gray'),
                        ])
                        ->columns(3)
                        ->label('📋 Основна інформація'),

                        // 👤 ІНФОРМАЦІЯ ПРО КЛІЄНТА
                        Group::make([
                            TextEntry::make('member.full_name')
                                ->label('ПІБ Клієнта')
                                ->color('primary')
                                ->weight('bold'),
                            TextEntry::make('member.phone')
                                ->label('Телефон')
                                ->color('primary'),
                            TextEntry::make('member.email')
                                ->label('Email')
                                ->color('primary'),
                        ])
                        ->columns(3)
                        ->label('👤 Інформація про клієнта'),

                        // 💰 ФІНАНСОВА ІНФОРМАЦІЯ
                        Group::make([
                            TextEntry::make('total_amount')
                                ->label('Загальна сума')
                                ->money('UAH')
                                ->color('info')
                                ->weight('bold')
                                ->size('lg'),
                            TextEntry::make('discount_percent')
                                ->label('Знижка %')
                                ->formatStateUsing(fn ($state) => $state ? $state . '%' : '0%')
                                ->color('success'),
                            TextEntry::make('discount_amount')
                                ->label('Сума знижки')
                                ->money('UAH')
                                ->color('success'),
                            TextEntry::make('final_amount')
                                ->label('До сплати')
                                ->money('UAH')
                                ->color('primary')
                                ->weight('bold'),
                        ])
                        ->columns(4),

                        Group::make([
                            TextEntry::make('paid_amount')
                                ->label('Сплачено')
                                ->money('UAH')
                                ->color('success')
                                ->weight('bold'),
                            TextEntry::make('remaining_amount')
                                ->label('Залишок')
                                ->money('UAH')
                                ->color(fn ($record) => $record->remaining_amount > 0 ? 'danger' : 'success')
                                ->weight('bold'),
                            TextEntry::make('paymentStatusName')
                                ->label('Статус оплати')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Не оплачено' => 'danger',
                                    'Частково оплачено' => 'warning',
                                    'Оплачено' => 'success',
                                    'Переплачено' => 'info',
                                }),
                        ])
                        ->columns(3)
                        ->label('💰 Фінансова інформація'),

                        // 🛒 ТОВАРИ ЗАМОВЛЕННЯ
                        Group::make([
                            TextEntry::make('orderItems')
                                ->label('Товари замовлення')
                                ->formatStateUsing(function ($record) {
                                    if ($record->orderItems->isEmpty()) {
                                        return 'Немає товарів';
                                    }
                                    
                                    $items = [];
                                    $totalItems = 0;
                                    $totalAmount = 0;
                                    
                                    foreach ($record->orderItems as $index => $item) {
                                        $productName = $item->product->name;
                                        if ($item->productOption) {
                                            $productName .= ' (' . $item->productOption->name . ')';
                                        }
                                        
                                        $itemTotal = $item->quantity * $item->price;
                                        $totalItems += $item->quantity;
                                        $totalAmount += $itemTotal;
                                        
                                        $items[] = "**" . ($index + 1) . ".** {$productName}\n" .
                                                  "   Кількість: {$item->quantity} шт.\n" .
                                                  "   Ціна: " . number_format($item->price, 2) . " грн\n" .
                                                  "   Сума: **" . number_format($itemTotal, 2) . " грн**";
                                    }
                                    
                                    $items[] = "\n---\n**Всього товарів:** {$totalItems} шт.\n**Загальна сума:** " . number_format($totalAmount, 2) . " грн";
                                    
                                    return implode("\n\n", $items);
                                })
                                ->columnSpanFull()
                                ->markdown(),
                        ])
                        ->label('🛒 Товари замовлення'),

                        // 💳 ПЛАТЕЖНА ІНФОРМАЦІЯ
                        Group::make([
                            TextEntry::make('paymentType.name')
                                ->label('Тип оплати')
                                ->color('info'),
                            TextEntry::make('cashRegister.name')
                                ->label('Каса')
                                ->color('info'),
                        ])
                        ->columns(2)
                        ->label('💳 Платіжна інформація'),

                        // 📸 КВИТАНЦІЯ
                        Group::make([
                            ImageEntry::make('payment_receipt')
                                ->label('Фото квитанції')
                                ->height(300)
                                ->visible(fn ($record) => !empty($record->payment_receipt)),
                        ])
                        ->columns(1)
                        ->label('📸 Квитанція'),

                        // 🚚 ДАНІ ДОСТАВКИ
                        Group::make([
                            TextEntry::make('shipping_name')
                                ->label('ПІБ Отримувача')
                                ->color('primary')
                                ->weight('bold'),
                            TextEntry::make('shipping_phone')
                                ->label('Телефон отримувача')
                                ->color('primary'),
                            TextEntry::make('shipping_city')
                                ->label('Місто доставки')
                                ->color('primary'),
                            TextEntry::make('shipping_carrier')
                                ->label('Спосіб доставки')
                                ->color('info'),
                            TextEntry::make('shipping_office')
                                ->label('Відділення')
                                ->color('info'),
                        ])
                        ->columns(2)
                        ->label('🚚 Дані доставки'),

                        // 📝 НОТАТКИ
                        Group::make([
                            TextEntry::make('notes')
                                ->label('Нотатки')
                                ->columnSpanFull()
                                ->visible(fn ($record) => !empty($record->notes)),
                        ])
                        ->label('📝 Нотатки'),
                    ])
//                Tables\Actions\ViewAction::make(),
//                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('30s');
    }
}
