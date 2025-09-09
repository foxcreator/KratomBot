<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-currency-dollar class="w-5 h-5" />
                Фінансова інформація
            </div>
        </x-slot>
        
        <x-slot name="description">
            Автоматично розраховані показники на основі замовлень та платежів
        </x-slot>

        @php
            $data = $this->getFinancialData();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Баланс клієнта -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Баланс клієнта
                        </p>
                        <p class="text-2xl font-bold {{ $data['balance'] > 0 ? 'text-green-600' : ($data['balance'] < 0 ? 'text-red-600' : 'text-gray-600') }}">
                            {{ $data['formatted_balance'] }}
                        </p>
                    </div>
                    <div class="p-2 rounded-full {{ $data['balance'] > 0 ? 'bg-green-100 text-green-600' : ($data['balance'] < 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600') }}">
                        <x-heroicon-o-wallet class="w-6 h-6" />
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $data['balance'] > 0 ? 'Клієнт має переплату' : ($data['balance'] < 0 ? 'Клієнт має заборгованість' : 'Баланс нульовий') }}
                </p>
            </div>

            <!-- Загальна сума замовлень -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Загальна сума замовлень
                        </p>
                        <p class="text-2xl font-bold text-blue-600">
                            {{ $data['formatted_amount'] }}
                        </p>
                    </div>
                    <div class="p-2 rounded-full bg-blue-100 text-blue-600">
                        <x-heroicon-o-shopping-cart class="w-6 h-6" />
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Розраховується автоматично
                </p>
            </div>

            <!-- Кількість замовлень -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Кількість замовлень
                        </p>
                        <p class="text-2xl font-bold text-purple-600">
                            {{ number_format($data['total_orders_count'], 0, ',', ' ') }}
                        </p>
                    </div>
                    <div class="p-2 rounded-full bg-purple-100 text-purple-600">
                        <x-heroicon-o-document-text class="w-6 h-6" />
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Всього замовлень
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
