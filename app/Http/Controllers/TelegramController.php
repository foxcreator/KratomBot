<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Setting;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\FileUpload\InputFile;
use Mockery\Exception;
use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Order;
use App\Models\Brand;
use App\Models\ProductOption;
use App\Models\Subcategory;

class TelegramController extends Controller
{
    protected $telegram;
    protected $channelsUsername;
    protected $settings;

    // Додаю константи для станів оформлення
    const CHECKOUT_STATE = [
        'AWAIT_PAYMENT_TYPE' => 'await_payment_type',
        'AWAIT_RECEIPT_PHOTO' => 'await_receipt_photo',
        'AWAIT_SHIPPING_PHONE' => 'await_shipping_phone',
        'AWAIT_SHIPPING_CITY' => 'await_shipping_city',
        'AWAIT_SHIPPING_CARRIER' => 'await_shipping_carrier',
        'AWAIT_SHIPPING_OFFICE' => 'await_shipping_office',
        'AWAIT_SHIPPING_NAME' => 'await_shipping_name',
    ];

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $this->settings = Setting::all()->pluck('value', 'key')->toArray();
    }

    public function setWebhook()
    {
        $url = env('APP_URL').'/telegram/webhook';
        $response = $this->telegram->setWebhook(['url' => $url]);

        return response()->json($response);
    }
    public function webhook()
    {
        try {
            $update = Telegram::getWebhookUpdates();

            if ($update->isType('callback_query')) {
                $chatId = $update->getCallbackQuery()->getMessage()->getChat()->getId();
                $data = $update->getCallbackQuery()->getData();
                $this->handleCallback($chatId, $data);
            } elseif ($update->isType('message')) {
                $chatId = $update->getMessage()->getChat()->getId();
                $username = $update->getMessage()->getFrom()->getUsername();
                $text = $update->getMessage()->getText();

                Member::updateOrCreate(
                    ['telegram_id' => $chatId],
                    ['username' => $username]
                );

                // Додаю обробку фото
                if ($update->getMessage()->has('photo')) {
                    $photoSizes = $update->getMessage()->get('photo');
                    \Log::info('webhook: photoSizes', ['type' => gettype($photoSizes), 'photoSizes' => $photoSizes]);
                    if ($photoSizes instanceof \Illuminate\Support\Collection) {
                        $photoSizes = $photoSizes->toArray();
                    }
                    if (is_array($photoSizes) && count($photoSizes) > 0) {
                        $largestPhoto = $photoSizes[array_key_last($photoSizes)];
                        \Log::info('webhook: largestPhoto', ['largestPhoto' => $largestPhoto]);
                        $this->handlePhoto($chatId, $largestPhoto);
                        return;
                    }
                }

                if ($text === '/start') {
                    $this->sendWelcome($chatId, $username);
                } else {
                    $this->handleText($chatId, $text);
                }
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    private function sendWelcome($chatId, $username)
    {
        $rawText = !empty($this->settings['helloMessage']) ? $this->settings['helloMessage'] : "Вітаємо, {{ username }}!\n\nОберіть дію з меню нижче:";
        $text = $this->replacePlaceholders($rawText, ['username' => '@' . $username]);
        $this->sendMainMenu($chatId, $text);
        if (!empty($this->settings['channel'])) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->settings['channel']
            ]);
        }
    }

    private function sendMainMenu($chatId, $text = null)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $cartCount = $member ? $member->cart_items_count : 0;
        $keyboard = $this->getMainMenuKeyboard($chatId);
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text ?? '☝',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function showCart($chatId)
    {
        $member = Member::where('telegram_id', $chatId)
            ->with(['cartItems.productOption', 'cartItems.product'])
            ->first();
        if (!$member || $member->cartItems->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '🛒 Ваш кошик порожній',
                'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
            ]);
            return;
        }
        $message = "🛒 <b>Ваш кошик:</b>\n\n";
        $total = 0;
        $inlineKeyboard = [];
        foreach ($member->cartItems as $item) {
            $product = $item->product;
            $option = $item->productOption;
            $itemPrice = $option ? $option->price : $product->price;
            $itemTotal = $item->quantity * (float) $itemPrice;
            $total += $itemTotal;

            $message .= "📦 <b>{$product->name}</b>";
            if ($option) {
                $message .= " <em>({$option->name})</em>";
            }
            $message .= "\nКількість: {$item->quantity} шт.\n";
            $message .= "Ціна: {$itemPrice} грн × {$item->quantity} = <b>{$itemTotal} грн</b>\n\n";

            $inlineKeyboard[] = [
                ['text' => '➖', 'callback_data' => 'decrease_quantity_' . $item->id],
                ['text' => $item->quantity, 'callback_data' => 'quantity_' . $item->id],
                ['text' => '➕', 'callback_data' => 'increase_quantity_' . $item->id],
                ['text' => '🗑', 'callback_data' => 'remove_from_cart_' . $item->id]
            ];
        }

        $discountPercent = isset($this->settings['telegram_channel_discount']) ? (float)$this->settings['telegram_channel_discount'] : 0;

        if ($this->isUserSubscribedToChannel($chatId) && $discountPercent > 0) {
            $discountAmount = round($total * $discountPercent / 100, 2);
            $totalWithDiscount = $total - $discountAmount;
            $message .= "\n🎁 <b>Ваша знижка: {$discountPercent}% (-{$discountAmount} грн)</b>";
            $message .= "\n💸 <b>Сума зі знижкою: {$totalWithDiscount} грн</b>";
        } else {
            $totalWithDiscount = $total;
            $message .= "💰 <b>Загальна сума: {$total} грн</b>";
        }

        $message .= "💰 <b>Загальна сума: {$total} грн</b>";
        $inlineKeyboard[] = [
            ['text' => '💳 Оформити замовлення', 'callback_data' => 'checkout_cart'],
            ['text' => '🗑 Очистити корзину', 'callback_data' => 'clear_cart']
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
        ]);
    }

    private function checkoutCart($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if (!$member || $member->cartItems->isEmpty()) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => 'Кошик порожній'
            ]);
            return;
        }
        $activeOrders = Order::where('member_id', $member->id)
            ->whereIn('status', ['new', 'processing'])
            ->count();
        if ($activeOrders > 0) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "У вас вже є активне замовлення. Менеджер звʼяжеться з вами протягом 15 хвилин.",
                'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
            ]);
            return;
        }

        $hasOrders = Order::where('member_id', $member->id)->exists();
        $keyboard = [
            [['text' => '💳 Передплата', 'callback_data' => 'pay_type_prepaid']],
        ];
        if (!$hasOrders) {
            $keyboard[] = [['text' => '🚚 Накладений платіж', 'callback_data' => 'pay_type_cod']];
        }
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "Оберіть спосіб оплати:\n\n<b>Передплата</b> — оплата на картку, після чого ви надсилаєте фото квитанції.\n<b>Накладений платіж</b> — оплата при отриманні (доступно лише для першого замовлення).",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_PAYMENT_TYPE'];
        $state['cart_snapshot'] = $member->cartItems->map(function($item) {
            return [
                'product_id' => $item->product_id,
                'product_option_id' => $item->product_option_id,
                'quantity' => $item->quantity,
            ];
        })->toArray();
        $state['total'] = $member->cartItems->sum(function($item) {
            return $item->quantity * ($item->productOption ? $item->productOption->price : $item->product->price);
        });
        $member->checkout_state = $state;
        $member->save();

        

        
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_PAYMENT_TYPE'];
        $state['cart_snapshot'] = $member->cartItems->map(function($item) {
            return [
                'product_id' => $item->product_id,
                'product_option_id' => $item->product_option_id,
                'quantity' => $item->quantity,
            ];
        })->toArray();
        $state['total'] = $member->cartItems->sum(function($item) {
            return $item->quantity * ($item->productOption ? $item->productOption->price : $item->product->price);
        });
        $member->checkout_state = $state;
        $member->save();
    }

    private function clearCart($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();

        if ($member) {
            $member->cartItems()->delete();
        }

        Telegram::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQueryId(),
            'text' => 'Кошик очищений'
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '🗑 Кошик очищений',
            'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
        ]);
    }

    private function addToCart($chatId, $productId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $product = Product::find($productId);
        if (!$member || !$product) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => 'Помилка додавання товару'
            ]);
            return;
        }
        // Перевірка наявності такого запису
        $cartItem = CartItem::where('member_id', $member->id)
            ->where('product_id', $productId)
            ->whereNull('product_option_id')
            ->first();
        if ($cartItem) {
            $cartItem->increment('quantity');
        } else {
            try {
                CartItem::create([
                    'member_id' => $member->id,
                    'product_id' => $productId,
                    'quantity' => 1
                ]);
            } catch (\Exception $e) {
                // Якщо дубль — просто інкрементуємо
                $cartItem = CartItem::where('member_id', $member->id)
                    ->where('product_id', $productId)
                    ->whereNull('product_option_id')
                    ->first();
                if ($cartItem) {
                    $cartItem->increment('quantity');
                }
            }
        }
        Telegram::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQueryId(),
            'text' => "✅ {$product->name} додано в корзину"
        ]);
    }

    private function removeFromCart($chatId, $itemId)
    {
        $member = Member::where('telegram_id', $chatId)->first();

        if ($member) {
            CartItem::where('member_id', $member->id)
                   ->where('id', $itemId)
                   ->delete();
        }

        Telegram::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQueryId(),
            'text' => 'Товар видалено з корзини'
        ]);

        // Оновлюємо відображення корзини
        $this->updateCartMessage($chatId);
    }

    private function changeQuantity($chatId, $itemId, $change)
    {
        $member = Member::where('telegram_id', $chatId)->first();

        if (!$member) {
            return;
        }

        $cartItem = CartItem::where('member_id', $member->id)
                           ->where('id', $itemId)
                           ->first();

        if (!$cartItem) {
            return;
        }

        $newQuantity = $cartItem->quantity + $change;

        if ($newQuantity <= 0) {
            $cartItem->delete();
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => 'Товар видалено з корзини'
            ]);
        } else {
            $cartItem->update(['quantity' => $newQuantity]);
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => "Кількість оновлено: {$newQuantity}"
            ]);
        }

        // Оновлюємо відображення корзини
        $this->updateCartMessage($chatId);
    }

    private function updateCartMessage($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();

        if (!$member || $member->cartItems->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '🛒 Ваш кошик порожній',
                'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
            ]);
            return;
        }

        $message = "🛒 <b>Ваш кошик:</b>\n\n";
        $total = 0;
        $inlineKeyboard = [];

        foreach ($member->cartItems as $item) {
            $product = $item->product;
            $option = $item->productOption;
            $itemPrice = $option ? $option->price : $product->price;
            $itemTotal = $item->quantity * (float) $itemPrice;
            $total += $itemTotal;

            $message .= "📦 <b>{$product->name}</b>";
            if ($option) {
                $message .= " <em>({$option->name})</em>";
            }
            $message .= "\n   Кількість: {$item->quantity} шт.\n";
            $message .= "   Ціна: {$itemPrice} грн × {$item->quantity} = {$itemTotal} грн\n\n";

            $inlineKeyboard[] = [
                ['text' => '➖', 'callback_data' => 'decrease_quantity_' . $item->id],
                ['text' => $item->quantity, 'callback_data' => 'quantity_' . $item->id],
                ['text' => '➕', 'callback_data' => 'increase_quantity_' . $item->id],
                ['text' => '🗑', 'callback_data' => 'remove_from_cart_' . $item->id]
            ];
        }

        $message .= "💰 <b>Загальна сума: {$total} грн</b>";

        $inlineKeyboard[] = [
            ['text' => '💳 Оформити замовлення', 'callback_data' => 'checkout_cart'],
            ['text' => '🗑 Очистити корзину', 'callback_data' => 'clear_cart']
        ];
        $inlineKeyboard[] = [
            ['text' => '⬅️ Назад', 'callback_data' => 'back_to_previous']
        ];

        // Отримуємо ID повідомлення для оновлення
        $update = Telegram::getWebhookUpdates();
        $messageId = $update->getCallbackQuery()->getMessage()->getMessageId();

        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
        ]);
    }

    private function getCallbackQueryId()
    {
        $update = Telegram::getWebhookUpdates();
        return $update->getCallbackQuery()->getId();
    }

    private function handleText($chatId, $text)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if ($member && $member->checkout_state && isset($member->checkout_state['step'])) {
            $state = $member->checkout_state;
            $step = $state['step'];
            if ($step === self::CHECKOUT_STATE['AWAIT_SHIPPING_PHONE']) {
                $state['shipping_phone'] = $text;
                $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_CITY'];
                $member->checkout_state = $state;
                $member->save();
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Введіть місто для відправки:"
                ]);
                return;
            } elseif ($step === self::CHECKOUT_STATE['AWAIT_SHIPPING_CITY']) {
                $state['shipping_city'] = $text;
                $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_CARRIER'];
                $member->checkout_state = $state;
                $member->save();
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Оберіть поштового оператора:",
                    'reply_markup' => json_encode(['keyboard' => [['Нова Пошта']], 'resize_keyboard' => true])
                ]);
                return;
            } elseif ($step === self::CHECKOUT_STATE['AWAIT_SHIPPING_CARRIER']) {
                $state['shipping_carrier'] = $text;
                $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_OFFICE'];
                $member->checkout_state = $state;
                $member->save();
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Введіть номер відділення:",
                    'reply_markup' => json_encode(['remove_keyboard' => true])
                ]);
                return;
            } elseif ($step === self::CHECKOUT_STATE['AWAIT_SHIPPING_OFFICE']) {
                $state['shipping_office'] = $text;
                $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_NAME'];
                $member->checkout_state = $state;
                $member->save();
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Введіть ПІБ отримувача:"
                ]);
                return;
            } elseif ($step === self::CHECKOUT_STATE['AWAIT_SHIPPING_NAME']) {
                $state['shipping_name'] = $text;
                $member->checkout_state = $state;
                $member->save();
                // Перевіряємо, чи всі дані заповнені
                $requiredFields = [
                    'shipping_phone',
                    'shipping_city',
                    'shipping_carrier',
                    'shipping_office',
                    'shipping_name',
                ];
                $missing = [];
                foreach ($requiredFields as $field) {
                    if (empty($state[$field])) {
                        $missing[] = $field;
                    }
                }
                if (count($missing) === 0) {
                    $this->finalizeOrder($chatId, $state['payment_type'] ?? 'cod');
                } else {
                    // Якщо чогось не вистачає — просимо ввести ще раз
                    $fieldNames = [
                        'shipping_phone' => 'номер телефону',
                        'shipping_city' => 'місто',
                        'shipping_carrier' => 'поштовий оператор',
                        'shipping_office' => 'номер відділення',
                        'shipping_name' => 'ПІБ отримувача',
                    ];
                    $fieldsList = array_map(fn($f) => $fieldNames[$f], $missing);
                    $fieldsText = implode(', ', $fieldsList);
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Будь ласка, введіть: $fieldsText"
                    ]);
                }
                return;
            }
        }
        $replacements = ['username' => ($member && $member->username) ? '@' . $member->username : ''];

        switch ($text) {
            case '📂 Каталог':
                if ($member) {
                    $this->pushHistory($member);
                    $this->setCurrentState($member, ['type' => 'catalog']);
                }
                $this->sendCatalogMenu($chatId);
                break;
            case '🎁 Отримай знижку':
                $discountInfo = $this->settings['discount_info'] ?? 'Щоб отримати знижку, підпишіться на наш Telegram-канал!';
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $discountInfo,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                break;
            case '⬇️ Топ продажів':
                if ($member) {
                    $this->pushHistory($member);
                    $this->setCurrentState($member, ['type' => 'top']);
                }
                $products = Product::where('is_top_sales', true)->get();
                if ($products->count() > 0) {
                    foreach ($products as $index => $product) {
                        $caption = ($index+1) . ". <b>{$product->name}</b>\n";
                        if ($product->options && $product->options->count() > 0) {
                            $inlineKeyboard = [];
                            foreach ($product->options as $option) {
                                $inlineKeyboard[] = [
                                    ['text' => $option->name . ' — ' . $option->price . ' грн', 'callback_data' => 'choose_option_' . $option->id]
                                ];
                            }
                        } else {
                            $caption .= "💰 {$product->price} грн";
                            $inlineKeyboard = [
                                [
                                    ['text' => '🛒 Придбати зараз', 'callback_data' => 'buy_product_' . $product->id],
                                    ['text' => '➕ Додати в корзину', 'callback_data' => 'add_to_cart_' . $product->id]
                                ]
                            ];
                        }
                        $localPath = public_path($product->image_url);
                        if (file_exists($localPath)) {
                            $photo = InputFile::create($localPath, basename($localPath));
                        } else {
                            $photo = $product->image_url;
                        }
                        Telegram::sendPhoto([
                            'chat_id' => $chatId,
                            'photo' => $photo,
                            'caption' => $caption,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
                        ]);
                    }
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Топ продаж поки що порожній."
                    ]);
                }
                $this->sendMainMenu($chatId);
                break;
            case (preg_match('/^🛒 Кошик/', $text) ? true : false):
                $this->showCart($chatId);
                break;
            case '💳 Оформити замовлення':
                $this->checkoutCart($chatId);
                break;
            case '🗑 Очистити корзину':
                $this->clearCart($chatId);
                break;
            case '📘 Як замовити':
                $messageText = $this->settings['howOrdering'] ?? 'Інформація відсутня.';
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Інструкція як замовити:</b> \n\n" . $this->settings['howOrdering'],
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                $this->sendMainMenu($chatId);
                break;
            case '💳 Оплата':
                $messageText = $this->settings['payment'] ?? 'Інформація відсутня.';
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Інформація про оплату:</b> \n\n" . $this->settings['payment'],
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                $this->sendMainMenu($chatId);
                break;
            case '⭐️ Відгуки':
                $messageText = $this->settings['reviews'] ?? 'Відгуки відсутні.';
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->settings['reviews'],
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                $this->sendMainMenu($chatId);
                break;
            case '🌿 Moringa':
                if ($member) {
                    $this->pushHistory($member);
                    $this->setCurrentState($member, ['type' => 'moringa']);
                }
                $this->sendMoringaMenu($chatId);
                break;
            case '🧪 Аналоги':
                if ($member) {
                    $this->pushHistory($member);
                    $this->setCurrentState($member, ['type' => 'analogs']);
                }
                
                break;
            case '⬅️ Назад':
                $prev = $this->popHistory($member);
                if ($prev) {
                    if ($prev['type'] === 'subcategory' && isset($prev['id'])) {
                        $this->sendSubcategoryProductsMenu($chatId, $prev['id']);
                    } elseif ($prev['type'] === 'brand' && isset($prev['id'])) {
                        $this->sendBrandProductsMenu($chatId, $prev['id']);
                    } elseif ($prev['type'] === 'catalog') {
                        $this->sendCatalogMenu($chatId);
                    } elseif ($prev['type'] === 'main') {
                        $this->sendMainMenu($chatId);
                    } elseif ($prev['type'] === 'analogs') {
                        
                    } elseif ($prev['type'] === 'moringa') {
                        $this->sendMoringaMenu($chatId);
                    } else {
                        $this->sendMainMenu($chatId);
                    }
                } else {
                    $this->sendMainMenu($chatId);
                }
                break;
            case 'ℹ️ Про продукт':
            case '📘 Про товар':
                $brand = null;
                if ($member && $member->current_brand_id) {
                    $brand = Brand::find($member->current_brand_id);
                }
                if (!$brand) {
                    $brand = Brand::where('name', 'Moringa')->first();
                }
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $brand->description,
                    'reply_markup' => json_encode(['keyboard' => $this->getMoringaMenuKeyboard($brand, $chatId), 'resize_keyboard' => true])
                ]);
                break;
            case '💰 Прайс':
                $brand = null;
                if ($member && $member->current_brand_id) {
                    $brand = Brand::find($member->current_brand_id);
                }
                if (!$brand) {
                    $brand = Brand::where('name', 'Moringa')->first();
                }
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $brand->price,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMoringaMenuKeyboard($brand, $chatId), 'resize_keyboard' => true])
                ]);
                break;
            case '🛒 Товари категорії':
                $brand = null;
                if ($member && $member->current_brand_id) {
                    $brand = Brand::find($member->current_brand_id);
                }
                if (!$brand) {
                    $brand = Brand::where('name', 'Moringa')->first();
                }
                \Log::info('sendBrandProductsMenu', [
                    'brandId' => $brand->id,
                    'subcategories' => Subcategory::where('brand_id', $brand->id)->pluck('id', 'name')->toArray()
                ]);
                $this->sendBrandProductsMenu($chatId, $brand->id);
                break;
            default:
                $brand = Brand::where('name', $text)->first();
                if ($brand && $member) {
                    $this->pushHistory($member);
                    $this->setCurrentState($member, ['type' => 'brand', 'id' => $brand->id]);
                    $member->update(['current_brand_id' => $brand->id]);
                    $this->sendBrandAnalogMenu($chatId, $brand->id);
                    break;
                }

                if (str_starts_with($text, '🛒 Придбати ')) {
                    $productId = (int)str_replace('🛒 Придбати ', '', $text);
                    if ($member) {
                        Order::create([
                            'member_id' => $member->id,
                            'product_id' => $productId,
                            'status' => 'new',
                        ]);
                    }
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Дякуємо за замовлення! Менеджер звʼяжеться з вами найближчим часом.'
                    ]);
                    $this->sendMainMenu($chatId);
                    break;
                }
                
                break;
        }

        $subcategory = Subcategory::where('name', $text)->first();
        if ($subcategory) {
            if ($member) {
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'subcategory', 'id' => $subcategory->id]);
            }
            $this->sendSubcategoryProductsMenu($chatId, $subcategory->id);
            return;
        }
    }

    private function sendCatalogMenu($chatId)
    {
        $brands = Brand::all();
        $keyboard = [];
        foreach ($brands as $brand) {
            $keyboard[] = [$brand->name];
        }
        $keyboard[] = ['⬅️ Назад', $this->getCartButton($chatId)[0]];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Оберіть категорію товарів:',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function sendMoringaMenu($chatId)
    {
        $brand = Brand::where('id', 1)->first();
        $keyboard = $this->getMoringaMenuKeyboard($brand, $chatId);
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '☝',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function getMoringaMenuKeyboard($brand, $chatId)
    {
        return [
            ['ℹ️ Про продукт'],
            ['💰 Прайс'],
            ['🛒 Товари категорії'],
            ['⬅️ Назад', $this->getCartButton($chatId)[0]],
        ];
    }

    private function sendBrandAnalogMenu($chatId, $brandId)
    {
        $brand = Brand::find($brandId);
        $keyboard = [
            ['ℹ️ Про продукт'],
            ['💰 Прайс'],
            ['🛒 Товари категорії'],
            ['⬅️ Назад', $this->getCartButton($chatId)[0]],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Категорія: ' . $brand->name,
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function sendBrandProductsMenu($chatId, $brandId)
    {
        $subcategories = Subcategory::where('brand_id', $brandId)->get();
        if ($subcategories->count() > 0) {
            $keyboard = [];
            foreach ($subcategories as $subcategory) {
                $keyboard[] = [$subcategory->name];
            }
            $keyboard[] = ['⬅️ Назад', $this->getCartButton($chatId)[0]];
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Оберіть форму продукту і замовляйте зручно.',
                'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
            ]);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'У цієї категорії ще немає підкатегорій.'
            ]);
        }
    }

    private function sendSubcategoryProductsMenu($chatId, $subcategoryId)
    {
        $products = Product::where('subcategory_id', $subcategoryId)->get();
        $keyboard = [
            ['⬅️ Назад', $this->getCartButton($chatId)[0]],
        ];
        if ($products->count() > 0) {
            foreach ($products as $product) {
                $caption = "<b>{$product->name}</b>\n\n";
                $caption .= "{$product->description}\n\n";
                if ($product->options && $product->options->count() > 0) {
                    $inlineKeyboard = [];
                    foreach ($product->options as $option) {
                        $inlineKeyboard[] = [
                            ['text' => $option->name . ' — ' . $option->price . ' грн', 'callback_data' => 'choose_option_' . $option->id]
                        ];
                    }
                } else {
                    $caption .= "💰 {$product->price} грн";
                    $inlineKeyboard = [
                        [
                            ['text' => '🛒 Придбати зараз', 'callback_data' => 'buy_product_' . $product->id],
                            ['text' => '➕ Додати в корзину', 'callback_data' => 'add_to_cart_' . $product->id]
                        ]
                    ];
                }
                if (!empty($product->image_url)) {
                    $localPath = public_path($product->image_url);
                    if (file_exists($localPath)) {
                        $photo = InputFile::create($localPath, basename($localPath));
                    } else {
                        $photo = InputFile::create($product->image_url, basename($product->image_url));
                    }
                    Telegram::sendPhoto([
                        'chat_id' => $chatId,
                        'photo' => $photo,
                        'caption' => $caption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
                    ]);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $caption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
                    ]);
                }
            }
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "⬇️ Обирайте потрібну форму продукту: \n ⬇️ \n ⚖️ Грамовка \n Оберіть потрібну кількість грамів, додайте у кошик або натисніть 'Замовити зараз' 🛒",
                'parse_mode' =>'HTML',
                'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
            ]);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'У цій підкатегорії ще немає товарів.',
                'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
            ]);
        }
    }

    private function getMainMenuKeyboard($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $cartCount = $member ? $member->cart_items_count : 0;
        return [
            ['📂 Каталог', '⬇️ Топ продажів'],
            ['🎁 Отримай знижку'],
            ['📘 Як замовити', '💳 Оплата'],
            [$this->getCartButton($chatId)[0]],
            ['⭐️ Відгуки'],
        ];
    }

    private function handleCallback($chatId, $data)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if (str_starts_with($data, 'choose_option_')) {
            if ($member) {
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'option', 'id' => (int)str_replace('choose_option_', '', $data)]);
            }
            $optionId = (int)str_replace('choose_option_', '', $data);
            $option = ProductOption::find($optionId);
            if ($option) {
                $inlineKeyboard = [
                    [
                        ['text' => '🛒 Придбати зараз', 'callback_data' => 'buy_product_option_' . $option->id],
                        ['text' => '➕ Додати в корзину', 'callback_data' => 'add_to_cart_option_' . $option->id]
                    ]
                ];
                $caption = "<b>{$option->product->name}</b>\n\n";
                $caption .= "{$option->product->description}\n\n";
                $caption .= "<b>{$option->name}</b> — {$option->price} грн";
                $update = Telegram::getWebhookUpdates();
                $message = $update->getCallbackQuery()->getMessage();
                if ($message->has('photo')) {
                    Telegram::editMessageCaption([
                        'chat_id' => $chatId,
                        'message_id' => $message->getMessageId(),
                        'caption' => $caption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
                    ]);
                } else {
                    Telegram::editMessageText([
                        'chat_id' => $chatId,
                        'message_id' => $message->getMessageId(),
                        'text' => $caption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
                    ]);
                }
            }
        } elseif (str_starts_with($data, 'buy_product_option_')) {
            if ($member) {
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'option', 'id' => (int)str_replace('buy_product_option_', '', $data)]);
            }
            $optionId = (int)str_replace('buy_product_option_', '', $data);
            $this->checkoutDirectProductOption($chatId, $optionId);
            return;
        } elseif (str_starts_with($data, 'add_to_cart_option_')) {
            if ($member) {
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'option', 'id' => (int)str_replace('add_to_cart_option_', '', $data)]);
            }
            $optionId = (int)str_replace('add_to_cart_option_', '', $data);
            $this->addToCartOption($chatId, $optionId);
        } elseif (str_starts_with($data, 'buy_product_')) {
            if ($member) {
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'product', 'id' => (int)str_replace('buy_product_', '', $data)]);
            }
            $productId = (int)str_replace('buy_product_', '', $data);
            $this->checkoutDirectProduct($chatId, $productId);
            return;
        } elseif (str_starts_with($data, 'add_to_cart_')) {
            if ($member) {
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'product', 'id' => (int)str_replace('add_to_cart_', '', $data)]);
            }
            $productId = (int)str_replace('add_to_cart_', '', $data);
            $this->addToCart($chatId, $productId);
        } elseif (str_starts_with($data, 'remove_from_cart_')) {
            $itemId = (int)str_replace('remove_from_cart_', '', $data);
            $this->removeFromCart($chatId, $itemId);
        } elseif (str_starts_with($data, 'increase_quantity_')) {
            $itemId = (int)str_replace('increase_quantity_', '', $data);
            $this->changeQuantity($chatId, $itemId, 1);
        } elseif (str_starts_with($data, 'decrease_quantity_')) {
            $itemId = (int)str_replace('decrease_quantity_', '', $data);
            $this->changeQuantity($chatId, $itemId, -1);
        } elseif ($data === 'checkout_cart') {
            $this->checkoutCart($chatId);
        } elseif ($data === 'clear_cart') {
            $this->clearCart($chatId);
        } elseif ($data === 'back_to_previous') {
            $prev = $this->popHistory($member);
            if ($prev) {
                if ($prev['type'] === 'subcategory' && isset($prev['id'])) {
                    $this->sendSubcategoryProductsMenu($chatId, $prev['id']);
                } elseif ($prev['type'] === 'brand' && isset($prev['id'])) {
                    $this->sendBrandProductsMenu($chatId, $prev['id']);
                } elseif ($prev['type'] === 'catalog') {
                    $this->sendCatalogMenu($chatId);
                } elseif ($prev['type'] === 'main') {
                    $this->sendMainMenu($chatId);
                } elseif ($prev['type'] === 'analogs') {
                    
                } elseif ($prev['type'] === 'moringa') {
                    $this->sendMoringaMenu($chatId);
                } else {
                    $this->sendMainMenu($chatId);
                }
            } else {
                $this->sendMainMenu($chatId);
            }
            return;
        } elseif ($data === 'pay_type_prepaid') {
            $this->startPrepaidCheckout($chatId);
            return;
        } elseif ($data === 'pay_type_cod') {
            $this->startCodCheckout($chatId);
            return;
        } elseif (str_starts_with($data, 'show_subcategory_')) {
            if ($member) {
                $subcategoryId = (int)str_replace('show_subcategory_', '', $data);
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'subcategory', 'id' => $subcategoryId]);
                $this->sendSubcategoryProductsMenu($chatId, $subcategoryId);
            }
        } elseif ($member && str_starts_with($data, 'choose_brand_')) {
            $brandId = (int)str_replace('choose_brand_', '', $data);
            $this->pushHistory($member);
            $this->setCurrentState($member, ['type' => 'brand', 'id' => $brandId]);
            $member->update(['current_brand_id' => $brandId]);
            $this->sendBrandAnalogMenu($chatId, $brandId);
            return;
        }
        // Якщо callback це назва бренду
        $brand = Brand::where('name', $data)->first();
        if ($brand && $member) {
            $member->update(['current_brand_id' => $brand->id]);
            $this->sendBrandAnalogMenu($chatId, $brand->id);
            return;
        }
    }

    private function replacePlaceholders(?string $text, array $data): string
    {
        if (empty($text)) {
            return '';
        }
        foreach ($data as $key => $value) {
            $text = str_replace("{{{$key}}}", $value, $text);
            $text = str_replace("{{ {$key} }}", $value, $text);
        }
        return $text;
    }

    private function buyProductOption($chatId, $optionId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $option = ProductOption::find($optionId);
        $product = $option ? $option->product : null;
        $activeOrders = Order::where('member_id', $member->id)
            ->whereIn('status', ['new', 'processing'])
            ->count();
        if ($activeOrders == 0 && $member && $option && $product) {
            $order = Order::create([
                'member_id' => $member->id,
                'status' => 'new',
                'total_amount' => $option->price,
                'source' => 'direct',
                'notes' => 'Пряме замовлення варіанту товару'
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_option_id' => $option->id,
                'quantity' => 1,
                'price' => $option->price
            ]);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "✅ Замовлення успішно створено!\n\n📋 Номер замовлення: {$order->order_number}\n💰 Сума: {$order->formatted_total}\n\nМенеджер звʼяжеться з вами протягом 15 хвилин."
            ]);
            $this->sendMainMenu($chatId);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "У вас вже є активне замовлення. Менеджер звʼяжеться з вами протягом 15 хвилин."
            ]);
        }
    }

    private function addToCartOption($chatId, $optionId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $option = ProductOption::find($optionId);
        $product = $option ? $option->product : null;
        if (!$member || !$option || !$product) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => 'Помилка додавання варіанту товару'
            ]);
            return;
        }
        
        $cartItem = CartItem::where('member_id', $member->id)
            ->where('product_id', $product->id)
            ->where('product_option_id', $option->id)
            ->first();

        if ($cartItem) {
            $cartItem->increment('quantity');
        } else {
            try {
                CartItem::create([
                    'member_id' => $member->id,
                    'product_id' => $product->id,
                    'product_option_id' => $option->id,
                    'quantity' => 1
                ]);
            } catch (\Exception $e) {
                \Log::error('CartItem create error', [
                    'member_id' => $member->id,
                    'product_id' => $product->id,
                    'product_option_id' => $option->id,
                    'error' => $e->getMessage()
                ]);
                // Якщо дубль — просто інкрементуємо
                $cartItem = CartItem::where('member_id', $member->id)
                    ->where('product_id', $product->id)
                    ->where('product_option_id', $option->id)
                    ->first();
                if ($cartItem) {
                    $cartItem->increment('quantity');
                }
            }
        }
        Telegram::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQueryId(),
            'text' => "✅ {$product->name} ({$option->name}) додано в корзину"
        ]);
        // Повертаємо вибір грамовок для цього товару
        $inlineKeyboard = [];
        foreach ($product->options as $opt) {
            $inlineKeyboard[] = [
                ['text' => $opt->name . ' — ' . $opt->price . ' грн', 'callback_data' => 'choose_option_' . $opt->id]
            ];
        }
        $caption = "<b>{$product->name}</b>\n\n{$product->description}";
        $update = Telegram::getWebhookUpdates();
        $message = $update->getCallbackQuery()->getMessage();
        if ($message->has('photo')) {
            Telegram::editMessageCaption([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
            ]);
        } else {
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'text' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
            ]);
        }
    }

    private function startPrepaidCheckout($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_RECEIPT_PHOTO'];
        $state['payment_type'] = 'prepaid';
        $member->checkout_state = $state;
        $member->save();
        $requisites = $this->settings['payments'] ?? 'Реквізити для оплати: ...';
        $requisites = $this->formatCodeBlocks($requisites);

        $total = $state['total'] ?? 0;
        $discountPercent = isset($this->settings['telegram_channel_discount']) ? (float)$this->settings['telegram_channel_discount'] : 0;
        $isSubscribed = $this->isUserSubscribedToChannel($chatId);
        $totalText = '';
        if ($isSubscribed && $discountPercent > 0) {
            $discountAmount = round($total * $discountPercent / 100, 2);
            $totalWithDiscount = $total - $discountAmount;
            $totalText = "\n💸 <b>Сума до оплати зі знижкою:</b> <b>" . number_format($totalWithDiscount, 2) . " грн</b> (знижка {$discountPercent}% -{$discountAmount} грн)\n";
        } else {
            $totalText = "\n💸 <b>Сума до оплати:</b> <b>" . number_format($total, 2) . " грн</b>\n";
        }
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>Оплата замовлення</b>\n\n$totalText$requisites\n\nПісля оплати надішліть фото квитанції у цей чат.",
            'parse_mode' => 'HTML',
        ]);
    }

    private function startCodCheckout($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_PHONE'];
        $state['payment_type'] = 'cod';
        $member->checkout_state = $state;
        $member->save();
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "Введіть номер телефону для відправки (у форматі +380...)"
        ]);
    }

    private function finalizeOrder($chatId, $paymentType)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $state = $member->checkout_state;
        $cartSnapshot = $state['cart_snapshot'] ?? [];
        $total = $state['total'] ?? 0;
        $discountPercent = isset($this->settings['telegram_channel_discount']) ? (float)$this->settings['telegram_channel_discount'] : 0;
        $isSubscribed = $this->isUserSubscribedToChannel($chatId);
        $discountAmount = 0;
        $totalWithDiscount = $total;
        if ($isSubscribed && $discountPercent > 0) {
            $discountAmount = round($total * $discountPercent / 100, 2);
            $totalWithDiscount = $total - $discountAmount;
        }
        $order = Order::create([
            'member_id' => $member->id,
            'status' => 'new',
            'total_amount' => $total,
            'source' => 'cart',
            'notes' => 'Замовлення з бота',
            'payment_type' => $state['payment_type'] ?? $paymentType,
            'payment_receipt' => $state['payment_receipt'] ?? null,
            'shipping_phone' => $state['shipping_phone'] ?? null,
            'shipping_city' => $state['shipping_city'] ?? null,
            'shipping_carrier' => $state['shipping_carrier'] ?? null,
            'shipping_office' => $state['shipping_office'] ?? null,
            'shipping_name' => $state['shipping_name'] ?? null,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
        ]);
        foreach ($cartSnapshot as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_option_id' => $item['product_option_id'],
                'quantity' => $item['quantity'],
                'price' => $item['product_option_id'] ? ProductOption::find($item['product_option_id'])->price : Product::find($item['product_id'])->price,
            ]);
        }
        $member->cartItems()->delete();
        $member->checkout_state = null;
        $member->save();

        $order->refresh();
        $orderItems = $order->orderItems()->with(['product', 'productOption'])->get();
        $message = "✅ <b>Замовлення успішно оформлено!</b>\n\n";
        $message .= "📄 <b>Номер замовлення:</b> {$order->order_number}\n";
        
        $message .= "\n<b>Товари у замовленні:</b>\n";
        foreach ($orderItems as $item) {
            $product = $item->product;
            $option = $item->productOption;
            $itemPrice = $option ? $option->price : $product->price;
            $itemTotal = $item->quantity * (float) $itemPrice;
            $message .= "📦 <b>{$product->name}</b>";
            if ($option) {
                $message .= " <em>({$option->name})</em>";
            }
            $message .= "\nКількість: {$item->quantity} шт.\n";
            $message .= "Ціна: {$itemPrice} грн × {$item->quantity} = <b>{$itemTotal} грн</b>\n\n";
        }
        if ($discountPercent > 0 && $discountAmount > 0) {
            $message .= "🎁 <b>Ваша знижка: {$discountPercent}% (-{$discountAmount} грн)</b>\n";
            $message .= "💸 <b>Сума зі знижкою: {$totalWithDiscount} грн</b>\n";
        } else {
            $message .= "💰 <b>Сума:</b> {$order->formatted_total}\n";
        }
        $message .= "Менеджер звʼяжеться з вами найближчим часом.";
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
        ]);
    }

    private function handlePhoto($chatId, $photo)
    {
        \Log::info('handlePhoto: start', ['chatId' => $chatId, 'photo' => $photo]);
        $member = Member::where('telegram_id', $chatId)->first();
        if ($member && $member->checkout_state && isset($member->checkout_state['step']) && $member->checkout_state['step'] === self::CHECKOUT_STATE['AWAIT_RECEIPT_PHOTO']) {
            $state = $member->checkout_state;
            $fileId = $photo['file_id'] ?? null;
            \Log::info('handlePhoto: fileId', ['fileId' => $fileId]);
            if ($fileId) {
                try {
                    $file = Telegram::getFile(['file_id' => $fileId]);
                    $filePath = $file->get('file_path');
                    $localPath = storage_path('app/public/payments/' . uniqid('receipt_') . '.jpg');
                    $url = 'https://api.telegram.org/file/bot' . env('TELEGRAM_BOT_TOKEN') . '/' . $filePath;
                    $fileContent = @file_get_contents($url);
                    if ($fileContent === false) {
                        \Log::error('handlePhoto: file_get_contents failed', ['url' => $url]);
                    } else {
                        $result = @file_put_contents($localPath, $fileContent);
                        \Log::info('handlePhoto: file_put_contents', ['result' => $result, 'localPath' => $localPath]);
                        if ($result === false) {
                            \Log::error('handlePhoto: file_put_contents failed', ['localPath' => $localPath]);
                        } else {
                            $state['payment_receipt'] = basename($localPath);
                            $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_PHONE'];
                            $member->checkout_state = $state;
                            $member->save();
                            \Log::info('handlePhoto: state updated', ['state' => $state]);
                            Telegram::sendMessage([
                                'chat_id' => $chatId,
                                'text' => "Дякуємо! Тепер введіть номер телефону для відправки (у форматі +380...):"
                            ]);
                            return;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('handlePhoto: exception', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function checkoutDirectProduct($chatId, $productId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $product = Product::find($productId);
        if (!$member || !$product) return;
        $activeOrders = Order::where('member_id', $member->id)
            ->whereIn('status', ['new', 'processing'])
            ->count();
        if ($activeOrders > 0) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "У вас вже є активне замовлення. Менеджер звʼяжеться з вами протягом 15 хвилин."
            ]);
            return;
        }
        $hasOrders = Order::where('member_id', $member->id)->exists();
        $keyboard = [
            [['text' => '💳 Передплата', 'callback_data' => 'pay_type_prepaid']],
        ];
        if (!$hasOrders) {
            $keyboard[] = [['text' => '🚚 Накладений платіж', 'callback_data' => 'pay_type_cod']];
        }
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "Оберіть спосіб оплати:\n\n<b>Передплата</b> — оплата на картку, після чого ви надсилаєте фото квитанції.\n<b>Накладений платіж</b> — оплата при отриманні (доступно лише для першого замовлення).",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_PAYMENT_TYPE'];
        $state['cart_snapshot'] = [[
            'product_id' => $product->id,
            'product_option_id' => null,
            'quantity' => 1,
        ]];
        $state['total'] = $product->price;
        $member->checkout_state = $state;
        $member->save();
    }

    private function checkoutDirectProductOption($chatId, $optionId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $option = ProductOption::find($optionId);
        $product = $option ? $option->product : null;
        if (!$member || !$option || !$product) return;
        $activeOrders = Order::where('member_id', $member->id)
            ->whereIn('status', ['new', 'processing'])
            ->count();
        if ($activeOrders > 0) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "У вас вже є активне замовлення. Менеджер звʼяжеться з вами протягом 15 хвилин."
            ]);
            return;
        }
        $hasOrders = Order::where('member_id', $member->id)->exists();
        $keyboard = [
            [['text' => '💳 Передплата', 'callback_data' => 'pay_type_prepaid']],
        ];
        if (!$hasOrders) {
            $keyboard[] = [['text' => '🚚 Накладений платіж', 'callback_data' => 'pay_type_cod']];
        }
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "Оберіть спосіб оплати:\n\n<b>Передплата</b> — оплата на картку, після чого ви надсилаєте фото квитанції.\n<b>Накладений платіж</b> — оплата при отриманні (доступно лише для першого замовлення).",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_PAYMENT_TYPE'];
        $state['cart_snapshot'] = [[
            'product_id' => $product->id,
            'product_option_id' => $option->id,
            'quantity' => 1,
        ]];
        $state['total'] = $option->price;
        $member->checkout_state = $state;
        $member->save();
    }

    private function getCartButton($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $cartCount = $member ? $member->cart_items_count : 0;
        return ['🛒 Кошик' . ($cartCount > 0 ? " ({$cartCount})" : '')];
    }

    private function isUserSubscribedToChannel($chatId)
    {
        
        
        $channelUsername = $this->settings['telegram_channel_username'] ?? '@auraaashopp';
        Log::info($channelUsername);
        try {
            $member = $this->telegram->getChatMember([
                'chat_id' => $channelUsername,
                'user_id' => $chatId
            ]);
            Log::info($member->status);
            return $member->status !== 'left';
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    private function pushHistory($member)
    {
        $uiState = $member->ui_state ?? [];
        if (is_string($uiState)) $uiState = json_decode($uiState, true);
        $history = $uiState['history'] ?? [];
        if (is_string($history)) $history = json_decode($history, true);
        $current = $uiState['current'] ?? ['type' => 'main'];
        $history[] = $current;
        $uiState['history'] = $history;
        $member->ui_state = $uiState;
        $member->save();
    }

    private function setCurrentState($member, $state)
    {
        $uiState = $member->ui_state ?? [];
        if (is_string($uiState)) $uiState = json_decode($uiState, true);
        $uiState['current'] = $state;
        $member->ui_state = $uiState;
        $member->save();
    }

    private function popHistory($member)
    {
        $uiState = $member->ui_state ?? [];
        if (is_string($uiState)) $uiState = json_decode($uiState, true);
        $history = $uiState['history'] ?? [];
        if (is_string($history)) $history = json_decode($history, true);
        $prev = null;
        if (!empty($history)) {
            $prev = array_pop($history);
            $uiState['history'] = $history;
            $uiState['current'] = $prev;
            $member->ui_state = $uiState;
            $member->save();
        }
        return $prev;
    }

    private function formatCodeBlocks($text)
    {
        // Замінює всі фрагменти в бектиках на <code>...</code>
        return preg_replace_callback('/`([^`]+)`/', function ($matches) {
            return '<code>' . htmlspecialchars($matches[1]) . '</code>';
        }, $text);
    }
}
