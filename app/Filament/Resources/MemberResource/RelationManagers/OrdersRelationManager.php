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
            ->columns([
                TextColumn::make('order_number')
                    ->label('Номер замовлення')
                    ->sortable(),
                TextColumn::make('statusName')
                    ->label('Статус')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Сума')
                    ->money('UAH', true),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->infolist([
                        Group::make([
                            TextEntry::make('order_number')
                                ->label('Номер Замовлення')
                                ->color('primary'),
                            TextEntry::make('statusName')
                                ->label('Статус')
                                ->color('success'),
                        ])->columns(3),

                        Group::make([
                            TextEntry::make('member.full_name')
                                ->label('Клієнт')
                                ->color('primary'),
                            TextEntry::make('member.phone')
                                ->label('Телефон')
                                ->color('primary'),
                            TextEntry::make('member.email')
                                ->label('Email')
                                ->color('primary'),
                        ])->columns(3),

                        Group::make([
                            TextEntry::make('discount_percent')
                                ->label('Знижка %')
                                ->color('success'),
                            TextEntry::make('discount_amount')
                                ->label('Сума знижки')
                                ->money('UAH', true)
                                ->color('success'),
                            TextEntry::make('total_amount')
                                ->label('До оплати')
                                ->money('UAH', true)
                                ->color('success'),
                        ])->columns(4),

                        Group::make([
                            TextEntry::make('paymentType.name')
                                ->label('Тип оплати'),
                            TextEntry::make('cashRegister.name')
                                ->label('Каса'),
                        ])->columns(2),

                        Group::make([
                            ImageEntry::make('payment_receipt')
                                ->label('Фото квитанції')
                                ->height(300),
                        ])
                        ->columns(1),

                        Group::make([
                            TextEntry::make('shipping_name')
                                ->label('ПІБ Замовника')
                                ->color('primary'),
                            TextEntry::make('shipping_phone')
                                ->label('Номер телефону')
                                ->color('primary'),
                            TextEntry::make('shipping_city')
                                ->label('Місто')
                                ->color('primary'),
                            TextEntry::make('shipping_carrier')
                                ->label('Спосіб доставки')
                                ->color('primary'),
                            TextEntry::make('shipping_office')
                                ->label('Відділення')
                                ->color('primary'),
                        ])
                            ->columns(2)
                            ->label('Дані відправки'),

                        TextEntry::make('notes')
                            ->label('Нотатки')
                            ->columnSpanFull(),
                    ])
//                Tables\Actions\ViewAction::make(),
//                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }
}
