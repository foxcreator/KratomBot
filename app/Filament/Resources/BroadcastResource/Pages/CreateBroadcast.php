<?php

namespace App\Filament\Resources\BroadcastResource\Pages;

use App\Filament\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Services\BroadcastService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateBroadcast extends CreateRecord
{
    protected static string $resource = BroadcastResource::class;

    public function getTitle(): string
    {
        return 'Нова розсилка';
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Запустити розсилку');
    }

    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()->label('Запустити та створити нову');
    }

    protected function handleRecordCreation(array $data): Broadcast
    {
        /** @var BroadcastService $service */
        $service = app(BroadcastService::class);

        $audience = $data['audience'] ?? Broadcast::AUDIENCE_ALL;
        $excluded = $data['excluded_member_ids'] ?? [];
        $specificIds = array_map('intval', $data['specific_member_ids'] ?? []);

        $recipientsCount = $service->audienceQuery($audience, $specificIds)
            ->when(!empty($excluded), fn ($q) => $q->whereNotIn('id', $excluded))
            ->count();

        if ($recipientsCount === 0) {
            Notification::make()
                ->title('Немає одержувачів')
                ->body(
                    $audience === Broadcast::AUDIENCE_SPECIFIC
                        ? 'Не обрано жодного користувача. Розсилка не створена.'
                        : 'Після виключень не залишилось користувачів для відправки. Розсилка не створена.'
                )
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.audience' => 'Після виключень не залишилось одержувачів.',
            ]);
        }

        $broadcast = $service->create($data, auth()->id());
        $service->dispatch($broadcast);

        Notification::make()
            ->title('Розсилка запущена')
            ->body('Заплановано відправку для ' . $broadcast->total_recipients . ' одержувач(ів).')
            ->success()
            ->send();

        return $broadcast;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
