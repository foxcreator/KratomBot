@php
    /** @var \App\Models\Broadcast $record */
    $record = $getRecord();
    $percent = $record->progress_percent;
    $processed = $record->sent_count + $record->failed_count;
    $color = match ($record->status) {
        \App\Models\Broadcast::STATUS_COMPLETED => 'bg-success-500',
        \App\Models\Broadcast::STATUS_CANCELLED => 'bg-danger-500',
        \App\Models\Broadcast::STATUS_IN_PROGRESS => 'bg-warning-500',
        default => 'bg-gray-400',
    };
@endphp

<div class="w-full">
    <div class="flex justify-between text-xs mb-1 text-gray-600 dark:text-gray-300">
        <span>{{ $processed }} / {{ $record->total_recipients }}</span>
        <span>{{ $percent }}%</span>
    </div>
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
        <div class="{{ $color }} h-3 rounded-full transition-all duration-500"
             style="width: {{ $percent }}%"></div>
    </div>
</div>
