<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold">Щоденна розбивка підписок</h3>
                <p class="text-sm text-gray-500">Період: {{ $from }} - {{ $to }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left">Дата</th>
                            <th class="px-3 py-2 text-right">Запустили бота</th>
                            <th class="px-3 py-2 text-right">Підписались</th>
                            <th class="px-3 py-2 text-right">Відписались</th>
                            <th class="px-3 py-2 text-right">Чистий приріст</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2">{{ $row['date'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['bot_starts'] }}</td>
                                <td class="px-3 py-2 text-right text-success-600 dark:text-success-400">{{ $row['joins'] }}</td>
                                <td class="px-3 py-2 text-right text-danger-600 dark:text-danger-400">{{ $row['leaves'] }}</td>
                                <td class="px-3 py-2 text-right {{ $row['net'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ $row['net'] > 0 ? '+' : '' }}{{ $row['net'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-gray-500">
                                    За вибраний період подій не знайдено.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
