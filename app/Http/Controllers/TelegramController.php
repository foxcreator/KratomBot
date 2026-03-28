<?php

namespace App\Http\Controllers;

use App\Http\Services\TelegramOrderNotifier;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\OrderItem;
use App\Settings\TelegramSettings;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Order;
use App\Models\Brand;
use App\Models\ProductOption;
use App\Models\Subcategory;
use App\Models\PaymentMethod;

class TelegramController extends Controller
{
    protected $telegram;
    protected $channelsUsername;
    protected $settings;

    const CHECKOUT_STATE = [
        'AWAIT_PAYMENT_TYPE' => 'await_payment_type',
        'AWAIT_RECEIPT_PHOTO' => 'await_receipt_photo',
        'AWAIT_SHIPPING_PHONE' => 'await_shipping_phone',
        'AWAIT_SHIPPING_CITY' => 'await_shipping_city',
        'AWAIT_SHIPPING_OFFICE' => 'await_shipping_office',
        'AWAIT_SHIPPING_NAME' => 'await_shipping_name',
    ];

    const PRODUCTS_PER_PAGE = 5;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bots.mybot.token'));
        $this->settings = app(TelegramSettings::class);
    }

    public function setWebhook()
    {
        $url = config('app.url').'/telegram/webhook';
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
                $messageId = $update->getMessage()->getMessageId();

                $member = \App\Models\Member::query()->firstOrNew(['telegram_id' => $chatId]);

                $member->username = $username;
                if (is_null($member->full_name)) {
                    $member->full_name = $username;
                }

                // Зберігаємо ID повідомлення користувача для можливого видалення
                if ($messageId) {
                    $this->saveUserMessageId($member, $messageId);
                }

                $member->save();

                // --- Тимчасово вимкнено (продажі неактивні) ---
                // Обробка фото (квитанція оплати) вимкнена.
                // if ($update->getMessage()->has('photo')) {
                //     $photoSizes = $update->getMessage()->get('photo');
                //     if ($photoSizes instanceof \Illuminate\Support\Collection) {
                //         $photoSizes = $photoSizes->toArray();
                //     }
                //     if (is_array($photoSizes) && count($photoSizes) > 0) {
                //         $largestPhoto = $photoSizes[array_key_last($photoSizes)];
                //         $this->handlePhoto($chatId, $largestPhoto);
                //         return;
                //     }
                // }
                // --- Кінець вимкненого блоку ---
                if ($update->getMessage()->has('photo')) {
                    return;
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
        $rawText = !empty($this->settings->hello_message) ? $this->settings->hello_message : "Вітаємо, {{ username }}!\n\nОберіть дію з меню нижче:";
        $text = $this->replacePlaceholders($rawText, ['username' => '@' . $username]);
        $this->sendMainMenu($chatId, $text);
        if (!empty($this->settings->channel)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->settings->channel
            ]);
        }
    }

    private function sendMainMenu($chatId, $text = null)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => $text ?? '☝',
            'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
        ]);
    }

    private function showCart($chatId)
    {
        $member = Member::where('telegram_id', $chatId)
            ->with(['cartItems.productOption', 'cartItems.product'])
            ->first();
        if (!$member || $member->cartItems->isEmpty()) {
            $this->sendMessageWithCleanup($chatId, $member, [
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

        $discountPercent = isset($this->settings->telegram_channel_discount) ? (float)$this->settings->telegram_channel_discount : 0;

        $message .= "💰 <b>Загальна сума: {$total} грн</b>";

        if ($this->isUserSubscribedToChannel($chatId) && $discountPercent > 0) {
            $discountAmount = round($total * $discountPercent / 100, 2);
            $totalWithDiscount = $total - $discountAmount;
            $message .= "\n🎁 <b>Ваша знижка: {$discountPercent}% (-{$discountAmount} грн)</b>";
            $message .= "\n💸 <b>Сума зі знижкою: {$totalWithDiscount} грн</b>";
        }

        $inlineKeyboard[] = [
            ['text' => '💳 Замовити зараз', 'callback_data' => 'checkout_cart'],
            ['text' => '🗑 Очистити кошик', 'callback_data' => 'clear_cart']
        ];
        $inlineKeyboard[] = [
            ['text' => '⬅️ Назад', 'callback_data' => 'back_to_previous']
        ];
        $this->sendMessageWithCleanup($chatId, $member, [
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
            $inlineKeyboard = [
                [['text' => '🏠 До головного меню', 'callback_data' => 'back_to_main_menu']]
            ];
            $this->sendMessageWithCleanup($chatId, $member, [
                'chat_id' => $chatId,
                'text' => "У вас вже є активне замовлення. Будь ласка, дочекайтесь підтвердження попереднього ⏳\nНаш менеджер звʼяжеться з вами найближчим часом 📞",
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
            ]);
            return;
        }

        // Отримуємо активні варіанти оплати
        $paymentMethods = PaymentMethod::active()->get();
        
        $keyboard = [];
        foreach ($paymentMethods as $method) {
            $keyboard[] = [['text' => $method->name, 'callback_data' => 'pay_method_' . $method->id]];
        }
        
        // Додаємо накладений платіж тільки для нових клієнтів
        $hasOrders = Order::where('member_id', $member->id)->exists();
        if (!$hasOrders) {
            $keyboard[] = [['text' => '🚚 Накладений платіж', 'callback_data' => 'pay_type_cod']];
        }
        
        $keyboard[] = [['text' => '⬅️ Назад до кошика', 'callback_data' => 'back_to_cart']];
        
        $messageText = "Оберіть спосіб оплати:";
        
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => $messageText,
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
                'price' => $item->productOption ? $item->productOption->price : $item->product->price,
            ];
        })->toArray();
        $state['total'] = $member->cartItems->sum(function($item) {
            return $item->quantity * ($item->productOption ? $item->productOption->price : $item->product->price);
        });
        $member->checkout_state = $state;
        $member->save();
    }

    private function showClearCartConfirmation($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        
        $inlineKeyboard = [
            [
                ['text' => '✅ Так, очистити', 'callback_data' => 'confirm_clear_cart'],
                ['text' => '❌ Скасувати', 'callback_data' => 'cancel_clear_cart']
            ]
        ];
        
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => '🗑 Ви дійсно хочете очистити кошик?',
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
        ]);
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

        $this->sendMainMenu($chatId, '🗑 Кошик очищений');
    }

    private function addToCart($chatId, $productId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $product = Product::find($productId);
        if (!$member || !$product || !$product->is_visible) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => 'Товар недоступний'
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

        // Показуємо основне меню після додавання товару в кошик
        $this->sendMainMenu($chatId, "✅ Товар додано в кошик!");
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

        $this->updateCartMessage($chatId);
    }

    private function updateCartMessage($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();

        if (!$member || $member->cartItems->isEmpty()) {
            $this->sendMainMenu($chatId, '🛒 Ваш кошик порожній');
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

        $discountPercent = isset($this->settings->telegram_channel_discount) ? (float)$this->settings->telegram_channel_discount : 0;

        $message .= "💰 <b>Загальна сума: {$total} грн</b>";

        if ($this->isUserSubscribedToChannel($chatId) && $discountPercent > 0) {
            $discountAmount = round($total * $discountPercent / 100, 2);
            $totalWithDiscount = $total - $discountAmount;
            $message .= "\n🎁 <b>Ваша знижка: {$discountPercent}% (-{$discountAmount} грн)</b>";
            $message .= "\n💸 <b>Сума зі знижкою: {$totalWithDiscount} грн</b>";
        }

        $inlineKeyboard[] = [
            ['text' => '💳 Оформити замовлення', 'callback_data' => 'checkout_cart'],
            ['text' => '🗑 Очистити корзину', 'callback_data' => 'clear_cart']
        ];
        $inlineKeyboard[] = [
            ['text' => '⬅️ Назад', 'callback_data' => 'back_to_previous']
        ];

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
        // --- Тимчасово вимкнено (продажі неактивні) ---
        // Обробка checkout state (введення телефону, міста, відділення, ПІБ) вимкнена.
        // Якщо у користувача застряг checkout_state — очищуємо його.
        if ($member && $member->checkout_state) {
            $member->checkout_state = null;
            $member->save();
        }
        // --- Кінець вимкненого блоку ---
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
                $discountInfo = $this->settings->discount_info ?? 'Щоб отримати знижку, підпишіться на наш Telegram-канал!';
                $this->sendMessageWithCleanup($chatId, $member, [
                    'chat_id' => $chatId,
                    'text' => $discountInfo,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                break;
            case '🔥 Топ продажів':
                if ($member) {
                    $this->pushHistory($member);
                    $this->setCurrentState($member, ['type' => 'top']);
                }
                $products = Product::where('is_top_sales', true)->where('is_visible', true)->get();
                if ($products->count() > 0) {
                    foreach ($products as $index => $product) {
                        $caption = ($index+1) . ". <b>{$product->name}</b>\n";
                        if ($product->options && $product->options->count() > 0) {
                            $inlineKeyboard = [];
                            foreach ($product->options as $option) {
                                // Тимчасово вимкнено (продажі неактивні) — показуємо тільки інформацію
                                $inlineKeyboard[] = [
                                    ['text' => $option->name . ' — ' . $option->price . ' грн', 'callback_data' => 'noop']
                                ];
                            }
                        } else {
                            $caption .= "💰 {$product->price} грн";
                            // Тимчасово вимкнено (продажі неактивні):
                            $inlineKeyboard = [];
                        }
                        $localPath = public_path('/storage/'.$product->image_url);
                        if (file_exists($localPath)) {
                            $photo = InputFile::create($localPath, basename($localPath));
                        } else {
                            $photo = $product->image_url;
                        }
                        $photoParams = [
                            'chat_id' => $chatId,
                            'photo' => $photo,
                            'caption' => $caption,
                            'parse_mode' => 'HTML',
                        ];
                        if (!empty($inlineKeyboard)) {
                            $photoParams['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
                        }
                        Telegram::sendPhoto($photoParams);
                    }

                    $this->sendMessageWithCleanup($chatId, $member, [
                        'chat_id' => $chatId,
                        'text' => "Хіти продажів — найулюбленіші товари наших клієнтів 🏆"
                    ]);
                } else {
                    $this->sendMainMenu($chatId, "Топ продажів поки що порожній.");
                }
                break;
            // --- Тимчасово вимкнено (продажі неактивні) ---
            // case (preg_match('/^🛒 Кошик/', $text) ? true : false):
            //     if ($member) {
            //         $this->pushHistory($member);
            //         $this->setCurrentState($member, ['type' => 'cart']);
            //     }
            //     $this->showCart($chatId);
            //     break;
            // case '💳 Оформити замовлення':
            //     $this->checkoutCart($chatId);
            //     break;
            // case '🗑 Очистити корзину':
            //     $this->clearCart($chatId);
            //     break;
            // --- Кінець вимкненого блоку ---
            case '📘 Як замовити':
                $messageText = $this->settings->how_ordering ?? 'Інформація відсутня.';
                $this->sendMessageWithCleanup($chatId, $member, [
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                break;
            case '💳 Оплата':
                $messageText = $this->settings->payments ?? 'Інформація відсутня.';
                $this->sendMessageWithCleanup($chatId, $member, [
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                break;
            case '⭐️ Відгуки':
                $messageText = $this->settings->reviews ?? 'Відгуки відсутні.';
                $this->sendMessageWithCleanup($chatId, $member, [
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
                ]);
                break;
            case '⬅️ Назад':
                $prev = $this->popHistory($member);
                // Очищуємо checkout_state при поверненні
                if ($member && $member->checkout_state) {
                    $member->checkout_state = null;
                    $member->save();
                }
                if ($prev) {
                    if ($prev['type'] === 'subcategory' && isset($prev['id'])) {
                        $this->sendSubcategoryProductsMenu($chatId, $prev['id']);
                    } elseif ($prev['type'] === 'brand' && isset($prev['id'])) {
                        $this->sendBrandProductsMenu($chatId, $prev['id']);
                    } elseif ($prev['type'] === 'catalog') {
                        $this->sendCatalogMenu($chatId);
                    } elseif ($prev['type'] === 'main') {
                        $this->sendMainMenu($chatId);
                    } elseif ($prev['type'] === 'brand_menu') {
                        Log::info('fucking fuck');
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
                $this->sendMessageWithCleanup($chatId, $member, [
                    'chat_id' => $chatId,
                    'text' => $brand->description,
                    'reply_markup' => json_encode(['keyboard' => $this->sendBrandMenu($chatId), 'resize_keyboard' => true])
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

                $this->sendMessageWithCleanup($chatId, $member, [
                    'chat_id' => $chatId,
                    'text' => $brand->price,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->sendBrandMenu($chatId), 'resize_keyboard' => true])
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

                // --- Тимчасово вимкнено (продажі неактивні) ---
                // if (str_starts_with($text, '🛒 Придбати ')) {
                //     $productId = (int)str_replace('🛒 Придбати ', '', $text);
                //     if ($member) {
                //         Order::create([
                //             'member_id' => $member->id,
                //             'product_id' => $productId,
                //             'status' => 'new',
                //             'source' => 'bot',
                //         ]);
                //     }
                //     Telegram::sendMessage([
                //         'chat_id' => $chatId,
                //         'text' => 'Дякуємо за замовлення! Менеджер звʼяжеться з вами найближчим часом.'
                //     ]);
                //     $this->sendMainMenu($chatId);
                //     break;
                // }
                // --- Кінець вимкненого блоку ---

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
        $member = Member::where('telegram_id', $chatId)->first();
        $brands = Brand::all();
        $keyboard = [];
        foreach ($brands as $brand) {
            $keyboard[] = [$brand->name];
        }
        // $keyboard[] = ['⬅️ Назад', $this->getCartButton($chatId)[0]]; // тимчасово вимкнено (продажі неактивні)
        $keyboard[] = ['⬅️ Назад'];
        
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => 'Оберіть категорію товарів:',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function sendBrandAnalogMenu($chatId, $brandId)
    {
        $brand = Brand::find($brandId);
        $member = Member::where('telegram_id', $chatId)->first();
        if ($member) {
            $this->pushHistory($member);
            $this->setCurrentState($member, ['type' => 'brand', 'id' => $brandId]);
        }

        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => $brand->chat_text ?? '.',
            'reply_markup' => json_encode(['keyboard' => $this->sendBrandMenu($chatId), 'resize_keyboard' => true])
        ]);
    }

    private function sendBrandMenu($chatId)
    {
        return [
            ['ℹ️ Про продукт'],
            ['💰 Прайс'],
            ['🛒 Товари категорії'],
            // ['⬅️ Назад', $this->getCartButton($chatId)[0]], // тимчасово вимкнено (продажі неактивні)
            ['⬅️ Назад'],
        ];
    }

    private function sendBrandProductsMenu($chatId, $brandId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $subcategories = Subcategory::where('brand_id', $brandId)->get();
        if ($subcategories->count() > 0) {
            $keyboard = [];
            foreach ($subcategories as $subcategory) {
                $keyboard[] = [$subcategory->name];
            }
            // $keyboard[] = ['⬅️ Назад', $this->getCartButton($chatId)[0]]; // тимчасово вимкнено (продажі неактивні)
            $keyboard[] = ['⬅️ Назад'];
            
            $this->sendMessageWithCleanup($chatId, $member, [
                'chat_id' => $chatId,
                'text' => 'Оберіть форму продукту і замовляйте зручно.',
                'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
            ]);
        } else {
            $this->sendMessageWithCleanup($chatId, $member, [
                'chat_id' => $chatId,
                'text' => 'У цієї категорії ще немає підкатегорій.'
            ]);
        }
    }

    private function sendSubcategoryProductsMenu($chatId, $subcategoryId)
    {
        $this->sendSubcategoryProductsPaginated($chatId, $subcategoryId, 1);
    }

    private function sendSubcategoryProductsPaginated($chatId, $subcategoryId, $page = 1)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $subcategory = Subcategory::find($subcategoryId);
        
        if (!$subcategory) {
            $this->sendMainMenu($chatId, 'Підкатегорія не знайдена.');
            return;
        }

        $products = Product::where('subcategory_id', $subcategoryId)->where('is_visible', true)->get();
        $totalProducts = $products->count();
        
        if ($totalProducts === 0) {
            $this->sendMainMenu($chatId, 'У цій підкатегорії ще немає товарів.');
            return;
        }

        $totalPages = ceil($totalProducts / self::PRODUCTS_PER_PAGE);
        $page = max(1, min($page, $totalPages));
        
        $offset = ($page - 1) * self::PRODUCTS_PER_PAGE;
        $productsForPage = $products->slice($offset, self::PRODUCTS_PER_PAGE);

        // Оновлюємо стан користувача
        if ($member) {
            $this->setCurrentState($member, [
                'type' => 'subcategory_products',
                'subcategory_id' => $subcategoryId,
                'page' => $page
            ]);
            
            $uiState = $member->ui_state ?? [];
            $uiState['pagination'] = [
                'subcategory_id' => $subcategoryId,
                'current_page' => $page,
                'total_pages' => $totalPages
            ];
            $member->ui_state = $uiState;
            $member->save();
        }

        // Створюємо inline-клавіатуру з товарами
        $inlineKeyboard = [];
        
        foreach ($productsForPage as $product) {
            $buttonText = $product->name;
            // Обрізаємо довгі назви
            if (strlen($buttonText) > 30) {
                $buttonText = substr($buttonText, 0, 27) . '...';
            }
            
            // Додаємо кожен товар в окремий рядок
            $inlineKeyboard[] = [['text' => $buttonText, 'callback_data' => 'show_product_' . $product->id]];
        }

        // Додаємо кнопки навігації
        $navigationRow = [];
        if ($page > 1) {
            $navigationRow[] = ['text' => '◀ Назад', 'callback_data' => 'navigate_products_' . $subcategoryId . '_' . ($page - 1) . '_prev'];
        }
        if ($page < $totalPages) {
            $navigationRow[] = ['text' => 'Вперед ▶', 'callback_data' => 'navigate_products_' . $subcategoryId . '_' . ($page + 1) . '_next'];
        }
        
        if (!empty($navigationRow)) {
            $inlineKeyboard[] = $navigationRow;
        }

        // Додаємо кнопку повернення
        $inlineKeyboard[] = [['text' => '⬅️ Назад до каталогу', 'callback_data' => 'back_to_catalog']];

        $message = "🛍 <b>Товари підкатегорії \"{$subcategory->name}\"</b>\n";
        $message .= "Сторінка {$page} з {$totalPages}";

        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
        ]);
    }

    private function showProductCard($chatId, $productId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $product = Product::find($productId);
        
        if (!$product || !$product->is_visible) {
            $this->sendMainMenu($chatId, 'Товар не знайдено або недоступний.');
            return;
        }

        // Оновлюємо стан користувача для відображення товару
        if ($member) {
            $this->setCurrentState($member, [
                'type' => 'product_card',
                'product_id' => $productId
            ]);
        }

        $caption = "<b>{$product->name}</b>\n\n";
        $caption .= "{$product->description}\n\n";
        
        if ($product->options && $product->options->count() > 0) {
            $inlineKeyboard = [];
            foreach ($product->options as $option) {
                $isAvailable = $option->in_stock && $option->current_quantity > 0;
                $buttonText = $option->name . ' — ' . $option->price . ' грн';
                if (!$isAvailable) {
                    $buttonText .= ' (немає в наявності)';
                }
                
                // Тимчасово вимкнено (продажі неактивні) — показуємо тільки інформацію про ціни
                $inlineKeyboard[] = [
                    ['text' => $buttonText, 'callback_data' => 'noop']
                ];
            }
        } else {
            $caption .= "💰 {$product->price} грн";
            $inlineKeyboard = [];
            // Тимчасово вимкнено (продажі неактивні):
            // $inlineKeyboard = [
            //     [
            //         ['text' => '🛒 Придбати зараз', 'callback_data' => 'buy_product_' . $product->id],
            //         ['text' => '➕ Додати в корзину', 'callback_data' => 'add_to_cart_' . $product->id]
            //     ]
            // ];
        }

        // Додаємо кнопку повернення до списку товарів
        $inlineKeyboard[] = [['text' => '⬅️ Назад до списку товарів', 'callback_data' => 'back_to_products_list']];

        if (!empty($product->image_url)) {
            $localPath = public_path('/storage/'.$product->image_url);
            if (file_exists($localPath)) {
                $photo = InputFile::create($localPath, basename($localPath));
            } else {
                $photo = InputFile::create($product->image_url, basename($product->image_url));
            }
            $this->sendMessageWithCleanup($chatId, $member, [
                'chat_id' => $chatId,
                'photo' => $photo,
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
            ]);
        } else {
            $this->sendMessageWithCleanup($chatId, $member, [
                'chat_id' => $chatId,
                'text' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
            ]);
        }
    }

    private function getMainMenuKeyboard($chatId)
    {
        return [
            ['📂 Каталог', '🔥 Топ продажів'],
            ['🎁 Отримай знижку'],
            ['📘 Як замовити', '💳 Оплата'],
            // ['🛒 Кошик'] — тимчасово вимкнено (продажі неактивні)
            // [$this->getCartButton($chatId)[0]],
            ['⭐️ Відгуки'],
        ];
    }

    private function handleCallback($chatId, $data)
    {
        Log::info($data);
        $member = Member::where('telegram_id', $chatId)->first();
        
        // Зберігаємо ID повідомлення користувача з callback query (якщо це повідомлення користувача)
        $update = Telegram::getWebhookUpdates();
        if ($update && $update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            if ($callbackQuery && $callbackQuery->getMessage()) {
                $messageId = $callbackQuery->getMessage()->getMessageId();
                // Перевіряємо чи це повідомлення користувача (не бота)
                $from = $callbackQuery->getFrom();
                if ($from && $from->getId() == $chatId) {
                    $this->saveUserMessageId($member, $messageId);
                }
            }
        }
        if (str_starts_with($data, 'choose_option_')) {
            if ($member) {
                $this->pushHistory($member);
                $this->setCurrentState($member, ['type' => 'option', 'id' => (int)str_replace('choose_option_', '', $data)]);
            }
            $optionId = (int)str_replace('choose_option_', '', $data);
            $option = ProductOption::find($optionId);
            if ($option) {
                // Тимчасово вимкнено (продажі неактивні):
                // $inlineKeyboard = [
                //     [
                //         ['text' => '🛒 Придбати зараз', 'callback_data' => 'buy_product_option_' . $option->id],
                //         ['text' => '➕ Додати в корзину', 'callback_data' => 'add_to_cart_option_' . $option->id]
                //     ]
                // ];
                $inlineKeyboard = [
                    [['text' => '⬅️ Назад до списку товарів', 'callback_data' => 'back_to_products_list']]
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
        // --- Тимчасово вимкнено (продажі неактивні) ---
        // } elseif (str_starts_with($data, 'buy_product_option_')) {
        //     ...checkoutDirectProductOption...
        // } elseif (str_starts_with($data, 'add_to_cart_option_')) {
        //     ...addToCartOption...
        // } elseif (str_starts_with($data, 'buy_product_')) {
        //     ...checkoutDirectProduct...
        // } elseif (str_starts_with($data, 'add_to_cart_')) {
        //     ...addToCart...
        // } elseif (str_starts_with($data, 'remove_from_cart_')) {
        //     ...removeFromCart...
        // } elseif (str_starts_with($data, 'increase/decrease_quantity_')) {
        //     ...changeQuantity...
        // } elseif ($data === 'checkout_cart') {
        //     ...checkoutCart...
        // } elseif ($data === 'clear_cart') {
        //     ...showClearCartConfirmation...
        // --- Кінець вимкненого блоку ---
        } elseif ($data === 'noop') {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
            ]);
            return;
        } elseif ($data === 'back_to_previous') {
            // Очищуємо checkout_state при поверненні
            if ($member && $member->checkout_state) {
                $member->checkout_state = null;
                $member->save();
            }
            $prev = $this->popHistory($member);
            if ($prev) {
                if ($prev['type'] === 'subcategory' && isset($prev['id'])) {
                    $this->sendSubcategoryProductsMenu($chatId, $prev['id']);
                } elseif ($prev['type'] === 'subcategory_products' && isset($prev['subcategory_id'])) {
                    $page = $prev['page'] ?? 1;
                    $this->sendSubcategoryProductsPaginated($chatId, $prev['subcategory_id'], $page);
                } elseif ($prev['type'] === 'brand' && isset($prev['id'])) {
                    $this->sendBrandProductsMenu($chatId, $prev['id']);
                } elseif ($prev['type'] === 'catalog') {
                    $this->sendCatalogMenu($chatId);
                } elseif ($prev['type'] === 'main') {
                    $this->sendMainMenu($chatId);
                } elseif ($prev['type'] === 'cart') {
                    $this->showCart($chatId);
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
        // --- Тимчасово вимкнено (продажі неактивні) ---
        // } elseif (str_starts_with($data, 'pay_method_')) {
        //     ...startPaymentMethodCheckout...
        // } elseif ($data === 'pay_type_prepaid') {
        //     ...startPrepaidCheckout...
        // } elseif ($data === 'pay_type_cod') {
        //     ...startCodCheckout...
        // } elseif ($data === 'back_to_cart') {
        //     ...showCart...
        // } elseif ($data === 'back_to_payment_selection') {
        //     ...checkoutCart...
        // } elseif ($data === 'back_to_phone_step') {
        //     ...handleBackToPhoneStep...
        // } elseif ($data === 'back_to_city_step') {
        //     ...handleBackToCityStep...
        // } elseif ($data === 'back_to_office_step') {
        //     ...handleBackToOfficeStep...
        // --- Кінець вимкненого блоку ---
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
        } elseif (str_starts_with($data, 'show_product_')) {
            // Показ карточки товару
            $productId = (int)str_replace('show_product_', '', $data);
            if ($member) {
                $this->pushHistory($member);
            }
            $this->showProductCard($chatId, $productId);
            return;
        } elseif (str_starts_with($data, 'navigate_products_')) {
            // Навігація між сторінками товарів (НЕ додаємо в історію)
            $parts = explode('_', $data);
            if (count($parts) >= 4) {
                $subcategoryId = (int)$parts[2];
                $page = (int)$parts[3];
                // НЕ додаємо в історію при навігації між сторінками
                $this->sendSubcategoryProductsPaginated($chatId, $subcategoryId, $page);
            }
            return;
        } elseif ($data === 'back_to_products_list') {
            // Повернення до списку товарів
            if ($member) {
                $prev = $this->popHistory($member);
                if ($prev && $prev['type'] === 'subcategory_products') {
                    $subcategoryId = $prev['subcategory_id'] ?? 1;
                    $page = $prev['page'] ?? 1;
                    $this->sendSubcategoryProductsPaginated($chatId, $subcategoryId, $page);
                } else {
                    $this->sendMainMenu($chatId);
                }
            } else {
                $this->sendMainMenu($chatId);
            }
            return;
        } elseif ($data === 'back_to_catalog') {
            // Пряме повернення до каталогу (очищуємо історію пагінації)
            if ($member) {
                // Знаходимо останній елемент історії типу 'catalog' або 'brand'
                $uiState = $member->ui_state ?? [];
                $history = $uiState['history'] ?? [];
                
                // Шукаємо останній каталог в історії
                $lastCatalog = null;
                for ($i = count($history) - 1; $i >= 0; $i--) {
                    if (in_array($history[$i]['type'], ['catalog', 'brand'])) {
                        $lastCatalog = $history[$i];
                        break;
                    }
                }
                
                if ($lastCatalog && $lastCatalog['type'] === 'brand' && isset($lastCatalog['id'])) {
                    $this->sendBrandProductsMenu($chatId, $lastCatalog['id']);
                } else {
                    $this->sendCatalogMenu($chatId);
                }
            } else {
                $this->sendCatalogMenu($chatId);
            }
            return;
        } elseif ($data === 'back_to_main_menu') {
            // Пряме повернення до головного меню (очищуємо історію та стан)
            if ($member) {
                // Очищуємо історію навігації та checkout_state
                $uiState = $member->ui_state ?? [];
                $uiState['history'] = [];
                $uiState['current'] = ['type' => 'main'];
                $member->ui_state = $uiState;
                $member->checkout_state = null; // Очищуємо стан оформлення замовлення
                $member->save();
            }
            $this->sendMainMenu($chatId, "🏠 Головне меню");
            return;
        // --- Тимчасово вимкнено (продажі неактивні) ---
        // } elseif ($data === 'confirm_clear_cart') {
        //     $this->clearCart($chatId);
        //     return;
        // } elseif ($data === 'cancel_clear_cart') {
        //     $this->showCart($chatId);
        //     return;
        // --- Кінець вимкненого блоку ---
        }

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

        // Показуємо основне меню після додавання товару в кошик
        $this->sendMainMenu($chatId, "✅ Товар додано в кошик!");
        $inlineKeyboard = [];
        foreach ($product->options as $opt) {
            $isAvailable = $opt->in_stock && $opt->current_quantity > 0;

            $inlineKeyboard[] = [
                [
                    'text' => $opt->name . ' — ' . $opt->price . ' грн' . (!$isAvailable ? ' (нема в наявності)' : ''),
                    'callback_data' => $isAvailable ? 'choose_option_' . $opt->id : 'noop',
                ]
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

    private function startPaymentMethodCheckout($chatId, $paymentMethodId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $paymentMethod = PaymentMethod::find($paymentMethodId);
        
        if (!$paymentMethod || !$paymentMethod->is_active) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => 'Варіант оплати не знайдено або неактивний'
            ]);
            return;
        }
        
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_RECEIPT_PHOTO'];
        $state['payment_type'] = 'prepaid';
        $state['payment_method_id'] = $paymentMethodId;
        $member->checkout_state = $state;
        $member->save();
        
        // Отримуємо реквізити з методу оплати
        $requisites = $paymentMethod->payment_details ?? 'Реквізити для оплати: ...';
        $requisites = $this->formatCodeBlocks($requisites);

        $total = $state['total'] ?? 0;
        $discountPercent = isset($this->settings->telegram_channel_discount) ? (float)$this->settings->telegram_channel_discount : 0;
        $isSubscribed = $this->isUserSubscribedToChannel($chatId);

        if ($isSubscribed && $discountPercent > 0) {
            $discountAmount = round($total * $discountPercent / 100, 2);
            $totalWithDiscount = $total - $discountAmount;
            $totalText = "\n💸 <b>Сума до оплати зі знижкою:</b> <b>" . number_format($totalWithDiscount, 2) . " грн</b> (знижка {$discountPercent}% -{$discountAmount} грн)\n";
        } else {
            $totalText = "\n💸 <b>Сума до оплати:</b> <b>" . number_format($total, 2) . " грн</b>\n";
        }
        
        $this->removeMainKeyboard($chatId);
        $keyboard = [
            [['text' => '⬅️ Назад до вибору оплати', 'callback_data' => 'back_to_payment_selection']]
        ];
        
        $messageText = "<b>Оплата замовлення</b>\n\n";
        $messageText .= "<b>Спосіб оплати:</b> {$paymentMethod->name}\n";
        $messageText .= $totalText;
        $messageText .= $requisites;
        $messageText .= "\n\nПісля оплати надішліть фото квитанції у цей чат.";
        
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => $messageText,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function startPrepaidCheckout($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_RECEIPT_PHOTO'];
        $state['payment_type'] = 'prepaid';
        $member->checkout_state = $state;
        $member->save();
        $requisites = $this->settings->payments ?? 'Реквізити для оплати: ...';
        $requisites = $this->formatCodeBlocks($requisites);

        $total = $state['total'] ?? 0;
        $discountPercent = isset($this->settings->telegram_channel_discount) ? (float)$this->settings->telegram_channel_discount : 0;
        $isSubscribed = $this->isUserSubscribedToChannel($chatId);

        if ($isSubscribed && $discountPercent > 0) {
            $discountAmount = round($total * $discountPercent / 100, 2);
            $totalWithDiscount = $total - $discountAmount;
            $totalText = "\n💸 <b>Сума до оплати зі знижкою:</b> <b>" . number_format($totalWithDiscount, 2) . " грн</b> (знижка {$discountPercent}% -{$discountAmount} грн)\n";
        } else {
            $totalText = "\n💸 <b>Сума до оплати:</b> <b>" . number_format($total, 2) . " грн</b>\n";
        }
        $this->removeMainKeyboard($chatId);
        $keyboard = [
            [['text' => '⬅️ Назад до вибору оплати', 'callback_data' => 'back_to_payment_selection']]
        ];
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => "<b>Оплата замовлення</b>\n\n$totalText$requisites\n\nПісля оплати надішліть фото квитанції у цей чат.",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
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
        $this->removeMainKeyboard($chatId);
        $keyboard = [
            [['text' => '⬅️ Назад до вибору оплати', 'callback_data' => 'back_to_payment_selection']]
        ];
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => "Введіть номер телефону для відправки (у форматі +380...)",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function finalizeOrder($chatId, $paymentType)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $state = $member->checkout_state;
        $cartSnapshot = $state['cart_snapshot'] ?? [];
        $total = $state['total'] ?? 0;
        $discountPercent = isset($this->settings->telegram_channel_discount) ? (float)$this->settings->telegram_channel_discount : 0;
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
            'final_amount' => $totalWithDiscount,
            'paid_amount' => $totalWithDiscount,  // Замовлення з бота оплачується одразу
            'remaining_amount' => 0,              // Залишок = 0 (все оплачено)
            'payment_status' => 'paid',           // Статус = оплачено
            'source' => 'bot',
            'notes' => 'Замовлення з бота (оплачено)',
            'payment_type' => $state['payment_type'] ?? $paymentType,
            'payment_method_id' => $state['payment_method_id'] ?? null,
            'payment_receipt' => $state['payment_receipt'] ?? null,
            'shipping_phone' => $state['shipping_phone'] ?? null,
            'shipping_city' => $state['shipping_city'] ?? null,
            'shipping_carrier' => $state['shipping_carrier'] ?? null,
            'shipping_office' => $state['shipping_office'] ?? null,
            'shipping_name' => $state['shipping_name'] ?? null,
            'discount_percent' => $isSubscribed ? $discountPercent : 0,
            'discount_amount' => $discountAmount,
        ]);
        foreach ($cartSnapshot as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_option_id' => $item['product_option_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'] ?? ($item['product_option_id'] ? ProductOption::find($item['product_option_id'])->price : Product::find($item['product_id'])->price),
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


        $notify = "🆕 <b>Нове замовлення</b>\n\n👤 Username: {$member->username}\n💰 Сума: $order->formatted_total \n\n" .
            config('app.url') . "/admin/orders/" . $order->id;
        app(TelegramOrderNotifier::class)->send($notify);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard($chatId), 'resize_keyboard' => true])
        ]);
    }

    private function handlePhoto($chatId, $photo)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if ($member && $member->checkout_state && isset($member->checkout_state['step']) && $member->checkout_state['step'] === self::CHECKOUT_STATE['AWAIT_RECEIPT_PHOTO']) {
            $state = $member->checkout_state;
            $fileId = $photo['file_id'] ?? null;
            if ($fileId) {
                try {
                    $file = Telegram::getFile(['file_id' => $fileId]);
                    $filePath = $file->get('file_path');
                    $localPath = storage_path('app/public/receipts/' . uniqid('receipt_') . '.jpg');
                    $url = 'https://api.telegram.org/file/bot' . config('telegram.bots.mybot.token') . '/' . $filePath;
                    $fileContent = @file_get_contents($url);
                    if ($fileContent === false) {
                        \Log::error('handlePhoto: file_get_contents failed', ['url' => $url]);
                    } else {
                        $result = @file_put_contents($localPath, $fileContent);
                        if ($result === false) {
                            \Log::error('handlePhoto: file_put_contents failed', ['localPath' => $localPath]);
                        } else {
                            $state['payment_receipt'] = 'receipts/' . basename($localPath);
                            $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_PHONE'];
                            $member->checkout_state = $state;
                            $member->save();
                            $this->removeMainKeyboard($chatId);
                            $this->sendMessageWithCleanup($chatId, $member, [
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
        if (!$member || !$product || !$product->is_visible) return;
        $activeOrders = Order::where('member_id', $member->id)
            ->whereIn('status', ['new', 'processing'])
            ->count();
        if ($activeOrders > 0) {
            $inlineKeyboard = [
                [['text' => '🏠 До головного меню', 'callback_data' => 'back_to_main_menu']]
            ];
            $this->sendMessageWithCleanup($chatId, $member, [
                'chat_id' => $chatId,
                'text' => "У вас вже є активне замовлення. Будь ласка, дочекайтесь підтвердження попереднього ⏳\nНаш менеджер звʼяжеться з вами найближчим часом 📞",
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
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
        $this->sendMessageWithCleanup($chatId, $member, [
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
            'price' => $product->price,
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
            $inlineKeyboard = [
                [['text' => '🏠 До головного меню', 'callback_data' => 'back_to_main_menu']]
            ];
            $this->sendMessageWithCleanup($chatId, $member, [
                'chat_id' => $chatId,
                'text' => "У вас вже є активне замовлення. Будь ласка, дочекайтесь підтвердження попереднього ⏳\nНаш менеджер звʼяжеться з вами найближчим часом 📞",
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
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
        $this->sendMessageWithCleanup($chatId, $member, [
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
            'price' => $option->price,
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
        $channelUsername = $this->settings->telegram_channel_username ?? '@auraaashopp';
        try {
            $member = $this->telegram->getChatMember([
                'chat_id' => $channelUsername,
                'user_id' => $chatId
            ]);
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
        return preg_replace_callback('/`([^`]+)`/', function ($matches) {
            return '<code>' . htmlspecialchars($matches[1]) . '</code>';
        }, $text);
    }

    /**
     * Видаляє попередні повідомлення для очищення чату
     */
    private function deletePreviousMessages($chatId, $member)
    {
        if (!$member) {
            return;
        }

        $uiState = $member->ui_state ?? [];
        if (is_string($uiState)) {
            $uiState = json_decode($uiState, true);
        }

        // Видаляємо повідомлення бота
        $messageIds = $uiState['message_ids'] ?? [];
        foreach ($messageIds as $messageId) {
            try {
                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $messageId
                ]);
            } catch (\Exception $e) {
                // Логуємо помилку, але не зупиняємо виконання
                Log::warning('Не вдалося видалити повідомлення бота', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Видаляємо повідомлення користувача
        $userMessageIds = $uiState['user_message_ids'] ?? [];
        foreach ($userMessageIds as $messageId) {
            try {
                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $messageId
                ]);
            } catch (\Exception $e) {
                // Логуємо помилку, але не зупиняємо виконання
                Log::warning('Не вдалося видалити повідомлення користувача', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Очищаємо списки повідомлень
        $uiState['message_ids'] = [];
        $uiState['user_message_ids'] = [];
        $member->ui_state = $uiState;
        $member->save();
    }

    /**
     * Зберігає ID нового повідомлення
     */
    private function saveMessageId($member, $messageId)
    {
        if (!$member || !$messageId) {
            return;
        }

        $uiState = $member->ui_state ?? [];
        if (is_string($uiState)) {
            $uiState = json_decode($uiState, true);
        }

        if (!isset($uiState['message_ids'])) {
            $uiState['message_ids'] = [];
        }

        $uiState['message_ids'][] = $messageId;
        $uiState['last_message_id'] = $messageId;
        $member->ui_state = $uiState;
        $member->save();
    }

    /**
     * Зберігає ID повідомлення користувача для можливого видалення
     */
    private function saveUserMessageId($member, $messageId)
    {
        if (!$member || !$messageId) {
            return;
        }

        $uiState = $member->ui_state ?? [];
        if (is_string($uiState)) {
            $uiState = json_decode($uiState, true);
        }

        if (!isset($uiState['user_message_ids'])) {
            $uiState['user_message_ids'] = [];
        }

        $uiState['user_message_ids'][] = $messageId;
        
        // Обмежуємо кількість збережених повідомлень користувача (останні 10)
        if (count($uiState['user_message_ids']) > 10) {
            $uiState['user_message_ids'] = array_slice($uiState['user_message_ids'], -10);
        }
        
        $member->ui_state = $uiState;
        $member->save();
    }

    /**
     * Відправляє повідомлення з очищенням попередніх
     */
    private function sendMessageWithCleanup($chatId, $member, $params)
    {
        // Видаляємо попередні повідомлення
        $this->deletePreviousMessages($chatId, $member);

        // Відправляємо нове повідомлення (фото або текст)
        if (isset($params['photo'])) {
            $response = Telegram::sendPhoto($params);
        } else {
            $response = Telegram::sendMessage($params);
        }

        // Зберігаємо ID нового повідомлення
        if (isset($response['message_id'])) {
            $this->saveMessageId($member, $response['message_id']);
        }

        return $response;
    }

    /**
     * Видаляє основну клавіатуру
     */
    private function removeMainKeyboard($chatId)
    {
        try {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => ' ',
                'reply_markup' => json_encode(['remove_keyboard' => true])
            ]);
        } catch (\Exception $e) {
            // Логуємо помилку, але не зупиняємо виконання
            Log::warning('Не вдалося видалити клавіатуру', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleBackToPhoneStep($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if (!$member) return;

        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_PHONE'];
        $member->checkout_state = $state;
        $member->save();

        $keyboard = [
            [['text' => '⬅️ Назад до вибору оплати', 'callback_data' => 'back_to_payment_selection']]
        ];
        $this->removeMainKeyboard($chatId);
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => "Введіть номер телефону для відправки (у форматі +380...)",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function handleBackToCityStep($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if (!$member) return;

        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_CITY'];
        $member->checkout_state = $state;
        $member->save();

        $keyboard = [
            [['text' => '⬅️ Назад до телефону', 'callback_data' => 'back_to_phone_step']]
        ];
        $this->removeMainKeyboard($chatId);
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => "Введіть місто для відправки:",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function handleBackToOfficeStep($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if (!$member) return;

        $state = $member->checkout_state ?? [];
        $state['step'] = self::CHECKOUT_STATE['AWAIT_SHIPPING_OFFICE'];
        $member->checkout_state = $state;
        $member->save();

        $keyboard = [
            [['text' => '⬅️ Назад до міста', 'callback_data' => 'back_to_city_step']]
        ];
        $this->removeMainKeyboard($chatId);
        $this->sendMessageWithCleanup($chatId, $member, [
            'chat_id' => $chatId,
            'text' => "Введіть номер відділення Нової Пошти:",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }
}
