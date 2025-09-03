<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Загальний огляд системи -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    📋 Загальний огляд системи EasySale
                </h2>
                <div class="prose dark:prose-invert max-w-none">
                    <p>EasySale - це комплексна система управління бізнесом, що включає:</p>
                    <ul>
                        <li><strong>Telegram бот</strong> для взаємодії з клієнтами</li>
                        <li><strong>Адмін-панель</strong> для управління бізнес-процесами</li>
                        <li><strong>Систему замовлень</strong> з корзиною та платежами</li>
                        <li><strong>Управління складом</strong> та постачаннями</li>
                        <li><strong>Систему заборгованості</strong> та фінансовий облік</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Основні розділи -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    🗂️ Основні розділи адмін-панелі
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-blue-600 dark:text-blue-400">📦 Склад</h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-300 mt-2 space-y-1">
                            <li>• Категорії (Бренди)</li>
                            <li>• Підкатегорії</li>
                            <li>• Товари</li>
                            <li>• Поставки</li>
                            <li>• Складські запаси</li>
                        </ul>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-green-600 dark:text-green-400">💰 Продажі</h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-300 mt-2 space-y-1">
                            <li>• Клієнти</li>
                            <li>• Замовлення</li>
                            <li>• Заборгованість</li>
                            <li>• Платежі</li>
                        </ul>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-purple-600 dark:text-purple-400">⚙️ Налаштування</h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-300 mt-2 space-y-1">
                            <li>• Користувачі</li>
                            <li>• Типи оплат</li>
                            <li>• Каси</li>
                            <li>• Telegram</li>
                            <li>• Документація</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Кейси використання -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    🎯 Кейси використання
                </h2>
                <div class="space-y-6">
                    <!-- Кейс 1: Створення замовлення -->
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 class="font-semibold text-blue-600 dark:text-blue-400 mb-2">📝 Створення замовлення вручну</h3>
                        <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                            <p><strong>Крок 1:</strong> Перейти в "Продажі" → "Замовлення" → "Створити"</p>
                            <p><strong>Крок 2:</strong> Вибрати клієнта, заповнити адресу доставки</p>
                            <p><strong>Крок 3:</strong> Натиснути "Створити" - автоматично перенаправить на редагування</p>
                            <p><strong>Крок 4:</strong> Внизу з'явиться таб "Товари" → "Додати товар"</p>
                            <p><strong>Крок 5:</strong> Вибрати товар, опцію, кількість, ціну → "Додати"</p>
                            <p class="text-green-600 dark:text-green-400"><strong>✅ Результат:</strong> Сума автоматично перерахується</p>
                        </div>
                    </div>

                    <!-- Кейс 2: Telegram бот -->
                    <div class="border-l-4 border-green-500 pl-4">
                        <h3 class="font-semibold text-green-600 dark:text-green-400 mb-2">🤖 Замовлення через Telegram бот</h3>
                        <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                            <p><strong>Крок 1:</strong> Клієнт натискає "📦 Каталог товарів"</p>
                            <p><strong>Крок 2:</strong> Вибирає категорію → підкатегорію → товар</p>
                            <p><strong>Крок 3:</strong> Натискає "➕ Додати в корзину"</p>
                            <p><strong>Крок 4:</strong> Переходить в "🛒 Кошик" → "💳 Оформити замовлення"</p>
                            <p><strong>Крок 5:</strong> Вводить контактні дані → підтверджує</p>
                            <p class="text-green-600 dark:text-green-400"><strong>✅ Результат:</strong> Замовлення з'являється в адмін-панелі</p>
                        </div>
                    </div>

                    <!-- Кейс 3: Платежі -->
                    <div class="border-l-4 border-yellow-500 pl-4">
                        <h3 class="font-semibold text-yellow-600 dark:text-yellow-400 mb-2">💳 Внесення платежу</h3>
                        <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                            <p><strong>Спосіб 1:</strong> "Заборгованість" → знайти клієнта → "Додати платіж"</p>
                            <p><strong>Спосіб 2:</strong> "Замовлення" → відкрити замовлення → таб "Платежі" → "Додати платіж"</p>
                            <p><strong>Спосіб 3:</strong> "Платежі" → "Створити" → вибрати клієнта та замовлення</p>
                            <p class="text-green-600 dark:text-green-400"><strong>✅ Результат:</strong> Автоматично оновлюється статус та суми</p>
                        </div>
                    </div>

                    <!-- Кейс 4: Склад -->
                    <div class="border-l-4 border-purple-500 pl-4">
                        <h3 class="font-semibold text-purple-600 dark:text-purple-400 mb-2">📦 Поповнення складу</h3>
                        <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                            <p><strong>Крок 1:</strong> "Склад" → "Поставки" → "Створити"</p>
                            <p><strong>Крок 2:</strong> Заповнити дані постачальника</p>
                            <p><strong>Крок 3:</strong> Таб "Товари поставки" → "Додати товар"</p>
                            <p><strong>Крок 4:</strong> Вибрати товар, опцію, кількість, ціну закупки</p>
                            <p class="text-green-600 dark:text-green-400"><strong>✅ Результат:</strong> Автоматично збільшується кількість на складі</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Система замовлень -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    🛒 Система замовлень
                </h2>
                <div class="space-y-4">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 class="font-semibold">Створення замовлення</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Замовлення можна створити вручну в адмін-панелі або через Telegram бот. 
                            При створенні автоматично встановлюється статус "Очікує оплати".
                        </p>
                    </div>
                    <div class="border-l-4 border-green-500 pl-4">
                        <h3 class="font-semibold">Додавання товарів</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>ВАЖЛИВО:</strong> Після створення замовлення потрібно зберегти його, 
                            тоді внизу з'явиться таб "Товари" з кнопкою "Додати товар". 
                            Сума автоматично перераховується при додаванні/видаленні товарів.
                        </p>
                    </div>
                    <div class="border-l-4 border-yellow-500 pl-4">
                        <h3 class="font-semibold">Статуси замовлення</h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-300">
                            <li><span class="inline-block w-3 h-3 bg-gray-400 rounded-full mr-2"></span>Очікує оплати</li>
                            <li><span class="inline-block w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>Частково оплачено</li>
                            <li><span class="inline-block w-3 h-3 bg-green-400 rounded-full mr-2"></span>Оплачено</li>
                            <li><span class="inline-block w-3 h-3 bg-blue-400 rounded-full mr-2"></span>Обробляється</li>
                            <li><span class="inline-block w-3 h-3 bg-gray-600 rounded-full mr-2"></span>Виконано</li>
                            <li><span class="inline-block w-3 h-3 bg-red-400 rounded-full mr-2"></span>Скасовано</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Система платежів -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    💳 Система платежів та заборгованості
                </h2>
                <div class="space-y-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h3 class="font-semibold text-blue-800 dark:text-blue-200">Способи внесення платежів</h3>
                        <div class="mt-2 space-y-2">
                            <div class="flex items-start">
                                <span class="inline-block w-6 h-6 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center mr-3 mt-0.5">1</span>
                                <div>
                                    <strong>Через "Заборгованість"</strong>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">Швидкий спосіб додати платіж для клієнта</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <span class="inline-block w-6 h-6 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center mr-3 mt-0.5">2</span>
                                <div>
                                    <strong>Через конкретне замовлення</strong>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">Платіж прив'язується до певного замовлення</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <span class="inline-block w-6 h-6 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center mr-3 mt-0.5">3</span>
                                <div>
                                    <strong>Через розділ "Платежі"</strong>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">Повний контроль над платежами</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                        <h3 class="font-semibold text-green-800 dark:text-green-200">Автоматичне оновлення</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            При внесенні платежу автоматично оновлюються:
                        </p>
                        <ul class="text-sm text-gray-600 dark:text-gray-300 mt-2 list-disc list-inside">
                            <li>Сума сплачено в замовленні</li>
                            <li>Залишок до сплати</li>
                            <li>Статус замовлення</li>
                            <li>Загальний борг клієнта</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Telegram бот -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    🤖 Telegram бот
                </h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="border rounded-lg p-4">
                            <h3 class="font-semibold text-blue-600 dark:text-blue-400">Функції бота</h3>
                            <ul class="text-sm text-gray-600 dark:text-gray-300 mt-2 space-y-1">
                                <li>• Перегляд каталогу товарів</li>
                                <li>• Додавання в корзину</li>
                                <li>• Оформлення замовлень</li>
                                <li>• Перегляд статусу замовлень</li>
                                <li>• Знижки для підписників</li>
                            </ul>
                        </div>
                        <div class="border rounded-lg p-4">
                            <h3 class="font-semibold text-green-600 dark:text-green-400">Корзина</h3>
                            <ul class="text-sm text-gray-600 dark:text-gray-300 mt-2 space-y-1">
                                <li>• Додавання/видалення товарів</li>
                                <li>• Зміна кількості</li>
                                <li>• Очищення корзини</li>
                                <li>• Оформлення замовлення</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Поширені помилки -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    🚨 Поширені помилки та рішення
                </h2>
                <div class="space-y-4">
                    <div class="border-l-4 border-red-500 pl-4">
                        <h3 class="font-semibold text-red-600 dark:text-red-400">Проблема: Не оновлюється сума замовлення</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Рішення:</strong> Перевірте, чи правильно додані товари в табі "Товари". 
                            Сума оновлюється автоматично при додаванні/видаленні товарів.
                        </p>
                    </div>
                    <div class="border-l-4 border-yellow-500 pl-4">
                        <h3 class="font-semibold text-yellow-600 dark:text-yellow-400">Проблема: Не можна додати товар</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Рішення:</strong> Перевірте наявність товару на складі. 
                            Якщо товар закінчився, поповніть склад через розділ "Поставки".
                        </p>
                    </div>
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 class="font-semibold text-blue-600 dark:text-blue-400">Проблема: Платіж не відображається</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Рішення:</strong> Перевірте, чи правильно вказано тип оплати та касу. 
                            Платіж має з'явитися в розділі "Платежі" та оновити замовлення.
                        </p>
                    </div>
                    <div class="border-l-4 border-purple-500 pl-4">
                        <h3 class="font-semibold text-purple-600 dark:text-purple-400">Проблема: Не з'являється таб "Товари"</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Рішення:</strong> Спочатку потрібно зберегти замовлення, тоді з'явиться таб "Товари" 
                            з кнопкою "Додати товар" внизу сторінки.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Рекомендації -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    💡 Рекомендації по використанню
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-green-600 dark:text-green-400 mb-3">✅ Рекомендується</h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                            <li>• Регулярно перевіряти залишки на складі</li>
                            <li>• Ведення обліку всіх платежів</li>
                            <li>• Оновлення статусів замовлень</li>
                            <li>• Резервне копіювання даних</li>
                            <li>• Використання фільтрів для пошуку</li>
                            <li>• Спочатку зберігати замовлення, потім додавати товари</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-red-600 dark:text-red-400 mb-3">❌ Не рекомендується</h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                            <li>• Видалення платежів без розуміння наслідків</li>
                            <li>• Зміна сум вручну без обґрунтування</li>
                            <li>• Ігнорування повідомлень про помилки</li>
                            <li>• Робота без резервного копіювання</li>
                            <li>• Надання доступу неавторизованим особам</li>
                            <li>• Додавання товарів до незабереженого замовлення</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Підтримка -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg p-6">
            <h2 class="text-lg font-medium mb-4">📞 Підтримка</h2>
            <p class="text-blue-100 mb-4">
                Якщо у вас виникли питання або проблеми з використанням системи, 
                звертайтеся до адміністратора системи.
            </p>
            <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex items-center">
                    <span class="mr-2">📧</span>
                    <span>support@easysale.com</span>
                </div>
                <div class="flex items-center">
                    <span class="mr-2">📱</span>
                    <span>+380 XX XXX XX XX</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
