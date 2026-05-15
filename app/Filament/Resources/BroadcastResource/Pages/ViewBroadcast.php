<?php

namespace App\Filament\Resources\BroadcastResource\Pages;

use App\Filament\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Services\BroadcastService;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBroadcast extends ViewRecord
{
    protected static string $resource = BroadcastResource::class;

    protected ?string $pollingInterval = '5s';

    public function getTitle(): string
    {
        return 'Розсилка #' . $this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Скасувати розсилку')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn () => !$this->record->isFinished())
                ->action(function () {
                    app(BroadcastService::class)->cancel($this->record);

                    Notification::make()
                        ->title('Розсилку скасовано')
                        ->body('Залишок повідомлень не буде відправлено.')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'sent_count',
                        'failed_count',
                        'finished_at',
                    ]);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Інформація про розсилку')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Статус')
                            ->formatStateUsing(fn ($state) => Broadcast::STATUSES[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                Broadcast::STATUS_PENDING => 'gray',
                                Broadcast::STATUS_IN_PROGRESS => 'warning',
                                Broadcast::STATUS_COMPLETED => 'success',
                                Broadcast::STATUS_CANCELLED => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('audience')
                            ->label('Аудиторія')
                            ->formatStateUsing(fn ($state) => Broadcast::AUDIENCES[$state] ?? $state)
                            ->badge()
                            ->color('info'),

                        TextEntry::make('author.name')
                            ->label('Автор')
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Створено')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('started_at')
                            ->label('Старт відправки')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('finished_at')
                            ->label('Завершено')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                Section::make('Прогрес')
                    ->schema([
                        ViewEntry::make('progress')
                            ->view('filament.broadcasts.progress'),

                        TextEntry::make('total_recipients')
                            ->label('Всього одержувачів'),
                        TextEntry::make('sent_count')
                            ->label('Успішно відправлено')
                            ->color('success'),
                        TextEntry::make('failed_count')
                            ->label('Помилки')
                            ->color('danger'),
                    ])
                    ->columns(3),

                Section::make('Текст повідомлення')
                    ->schema([
                        TextEntry::make('message')
                            ->label('')
                            ->html()
                            ->columnSpanFull(),
                        ImageEntry::make('image_path')
                            ->label('Зображення')
                            ->disk('public')
                            ->visible(fn (Broadcast $record): bool => !empty($record->image_path))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
