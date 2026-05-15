<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BroadcastResource\Pages;
use App\Models\Broadcast;
use App\Models\Member;
use App\Services\BroadcastService;
use App\Settings\TelegramSettings;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class BroadcastResource extends Resource
{
    protected static ?string $model = Broadcast::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Розсилка';
    protected static ?string $label = 'Розсилку';
    protected static ?string $pluralLabel = 'Розсилки';
    protected static ?string $navigationGroup = 'Розсилка';
    protected static ?int $navigationSort = 0;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return (bool) app(TelegramSettings::class)->show_broadcasts_group;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('audience')
                    ->label('Кому відправляти')
                    ->options(Broadcast::AUDIENCES)
                    ->default(Broadcast::AUDIENCE_ALL)
                    ->required()
                    ->reactive()
                    ->live(),

                RichEditor::make('message')
                    ->label('Текст повідомлення')
                    ->required()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'strike',
                        'link',
                        'blockquote',
                        'codeBlock',
                        'bulletList',
                        'orderedList',
                        'undo',
                        'redo',
                    ])
                    ->helperText('Виділіть текст і натисніть кнопку на панелі (Жирний, Курсив тощо), як у Word. Форматування автоматично відобразиться у повідомленні Telegram.')
                    ->columnSpanFull(),

                FileUpload::make('image_path')
                    ->label('Зображення (опціонально)')
                    ->image()
                    ->imageEditor()
                    ->directory('broadcasts')
                    ->disk('public')
                    ->maxSize(5120)
                    ->helperText('Якщо завантажене — буде надіслано фото з підписом. При тексті понад 1024 символи фото надсилається окремо.'),

                Select::make('excluded_member_ids')
                    ->label('Виключити з цієї розсилки')
                    ->helperText('Виберіть користувачів, яким НЕ потрібно надсилати саме цю розсилку. Це разове виключення, на майбутні розсилки воно не впливає.')
                    ->multiple()
                    ->searchable()
                    ->preload(false)
                    ->live()
                    ->getSearchResultsUsing(function (string $search, Get $get): array {
                        $audience = $get('audience') ?? Broadcast::AUDIENCE_ALL;

                        return app(BroadcastService::class)
                            ->audienceQuery($audience)
                            ->where(function ($q) use ($search) {
                                $like = '%' . $search . '%';
                                $q->where('full_name', 'like', $like)
                                  ->orWhere('username', 'like', $like)
                                  ->orWhere('phone', 'like', $like)
                                  ->orWhere('telegram_id', 'like', $like);
                            })
                            ->orderBy('full_name')
                            ->limit(50)
                            ->get(['id', 'full_name', 'username', 'telegram_id'])
                            ->mapWithKeys(fn (Member $m) => [
                                $m->id => static::memberOptionLabel($m),
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelsUsing(function (array $values): array {
                        return Member::query()
                            ->whereIn('id', $values)
                            ->get(['id', 'full_name', 'username', 'telegram_id'])
                            ->mapWithKeys(fn (Member $m) => [
                                $m->id => static::memberOptionLabel($m),
                            ])
                            ->toArray();
                    }),

                Placeholder::make('recipients_preview')
                    ->label('Кількість одержувачів')
                    ->content(function (Get $get): HtmlString {
                        $audience = $get('audience') ?? Broadcast::AUDIENCE_ALL;
                        $service = app(BroadcastService::class);

                        $total = $service->audienceQuery($audience)->count();
                        $excluded = (array) ($get('excluded_member_ids') ?? []);
                        $excludedCount = !empty($excluded)
                            ? $service->audienceQuery($audience)
                                ->whereIn('id', $excluded)
                                ->count()
                            : 0;

                        $willSend = max(0, $total - $excludedCount);

                        return new HtmlString(
                            'Буде відправлено: <span style="font-weight:600">' . $willSend . '</span> з '
                            . $total . ' користувачів (виключено: ' . $excludedCount . ').'
                        );
                    }),

                Placeholder::make('schedule_hint')
                    ->label('Інтервал відправки')
                    ->content('Повідомлення будуть надсилатись по черзі з випадковим інтервалом 30-60 секунд між одержувачами.'),
            ]);
    }

    protected static function memberOptionLabel(Member $member): string
    {
        $name = $member->full_name ?: 'Без імені';
        $extras = [];

        if ($member->username) {
            $extras[] = '@' . $member->username;
        }
        if ($member->telegram_id) {
            $extras[] = 'id ' . $member->telegram_id;
        }

        return $extras
            ? $name . ' (' . implode(', ', $extras) . ')'
            : $name;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Повідомлення')
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\TextColumn::make('audience')
                    ->label('Аудиторія')
                    ->formatStateUsing(fn ($state) => Broadcast::AUDIENCES[$state] ?? $state)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
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

                Tables\Columns\TextColumn::make('progress')
                    ->label('Прогрес')
                    ->state(function (Broadcast $record): string {
                        $processed = $record->sent_count + $record->failed_count;
                        return $processed . ' / ' . $record->total_recipients
                            . ' (' . $record->progress_percent . '%)';
                    }),

                Tables\Columns\TextColumn::make('sent_count')
                    ->label('Успішно')
                    ->color('success'),

                Tables\Columns\TextColumn::make('failed_count')
                    ->label('Помилки')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Автор')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Broadcast::STATUSES),
                Tables\Filters\SelectFilter::make('audience')
                    ->label('Аудиторія')
                    ->options(Broadcast::AUDIENCES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Деталі'),
                Tables\Actions\Action::make('cancel')
                    ->label('Скасувати')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Broadcast $record) => !$record->isFinished())
                    ->action(function (Broadcast $record) {
                        app(BroadcastService::class)->cancel($record);
                    }),
            ])
            ->bulkActions([])
            ->poll('10s');
    }

    public static function getRelations(): array
    {
        return [
            BroadcastResource\RelationManagers\RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBroadcasts::route('/'),
            'create' => Pages\CreateBroadcast::route('/create'),
            'view' => Pages\ViewBroadcast::route('/{record}'),
        ];
    }
}
