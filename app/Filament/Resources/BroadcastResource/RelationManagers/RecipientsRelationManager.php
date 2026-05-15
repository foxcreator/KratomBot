<?php

namespace App\Filament\Resources\BroadcastResource\RelationManagers;

use App\Models\BroadcastRecipient;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';
    protected static ?string $title = 'Одержувачі';
    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Імʼя')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.username')
                    ->label('Username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn ($state) => BroadcastRecipient::STATUSES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        BroadcastRecipient::STATUS_SENT => 'success',
                        BroadcastRecipient::STATUS_FAILED => 'danger',
                        BroadcastRecipient::STATUS_SKIPPED => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Час відправки')
                    ->dateTime('d.m.Y H:i:s')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Помилка')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(BroadcastRecipient::STATUSES),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('id', 'asc')
            ->poll('5s');
    }
}
