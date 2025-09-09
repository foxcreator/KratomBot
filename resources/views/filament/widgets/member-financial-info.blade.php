<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; gap: 8px;">
                <x-heroicon-o-currency-dollar style="width: 20px; height: 20px;" />
                Фінансова інформація
            </div>
        </x-slot>
        
        <x-slot name="description">
            Автоматично розраховані показники на основі замовлень та платежів
        </x-slot>

        @php
            $data = $this->getFinancialData();
        @endphp

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
            <!-- Баланс клієнта -->
            <div style="background: #f9fafb; border-radius: 8px; padding: 16px; border: 1px solid #e5e7eb;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <p style="font-size: 14px; font-weight: 500; color: #6b7280; margin: 0 0 8px 0;">
                            Баланс клієнта
                        </p>
                        <p style="font-size: 24px; font-weight: bold; margin: 0; color: {{ $data['balance'] > 0 ? '#059669' : ($data['balance'] < 0 ? '#dc2626' : '#6b7280') }};">
                            {{ $data['formatted_balance'] }}
                        </p>
                    </div>
                    <div style="padding: 8px; border-radius: 50%; background: {{ $data['balance'] > 0 ? '#d1fae5' : ($data['balance'] < 0 ? '#fee2e2' : '#f3f4f6') }}; color: {{ $data['balance'] > 0 ? '#059669' : ($data['balance'] < 0 ? '#dc2626' : '#6b7280') }};">
                        <x-heroicon-o-wallet style="width: 24px; height: 24px;" />
                    </div>
                </div>
                <p style="font-size: 12px; color: #9ca3af; margin: 8px 0 0 0;">
                    {{ $data['balance'] > 0 ? 'Клієнт має переплату' : ($data['balance'] < 0 ? 'Клієнт має заборгованість' : 'Баланс нульовий') }}
                </p>
            </div>

            <!-- Загальна сума замовлень -->
            <div style="background: #f9fafb; border-radius: 8px; padding: 16px; border: 1px solid #e5e7eb;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <p style="font-size: 14px; font-weight: 500; color: #6b7280; margin: 0 0 8px 0;">
                            Загальна сума замовлень
                        </p>
                        <p style="font-size: 24px; font-weight: bold; margin: 0; color: #2563eb;">
                            {{ $data['formatted_amount'] }}
                        </p>
                    </div>
                    <div style="padding: 8px; border-radius: 50%; background: #dbeafe; color: #2563eb;">
                        <x-heroicon-o-shopping-cart style="width: 24px; height: 24px;" />
                    </div>
                </div>
                <p style="font-size: 12px; color: #9ca3af; margin: 8px 0 0 0;">
                    Розраховується автоматично
                </p>
            </div>

            <!-- Кількість замовлень -->
            <div style="background: #f9fafb; border-radius: 8px; padding: 16px; border: 1px solid #e5e7eb;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <p style="font-size: 14px; font-weight: 500; color: #6b7280; margin: 0 0 8px 0;">
                            Кількість замовлень
                        </p>
                        <p style="font-size: 24px; font-weight: bold; margin: 0; color: #7c3aed;">
                            {{ number_format($data['total_orders_count'], 0, ',', ' ') }}
                        </p>
                    </div>
                    <div style="padding: 8px; border-radius: 50%; background: #ede9fe; color: #7c3aed;">
                        <x-heroicon-o-document-text style="width: 24px; height: 24px;" />
                    </div>
                </div>
                <p style="font-size: 12px; color: #9ca3af; margin: 8px 0 0 0;">
                    Всього замовлень
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
