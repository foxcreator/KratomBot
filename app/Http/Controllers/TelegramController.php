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

class TelegramController extends Controller
{
    protected $telegram;
    protected $channelsUsername;
    protected $settings;

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
                'text' => 'Перейдіть в наш канал: ' . $this->settings['channel']
            ]);
        }
    }

    private function sendMainMenu($chatId, $text = null)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $cartCount = $member ? $member->cart_items_count : 0;
        
        $keyboard = [
            ['📦 Каталог', '🔥 Топ продаж'],
            ['🛒 Корзина' . ($cartCount > 0 ? " ({$cartCount})" : '')],
            ['📘 Як замовити', '💳 Оплата'],
            ['⭐️ Відгуки'],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text ?? '☝',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function showCart($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        
        if (!$member || $member->cartItems->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '🛒 Ваша корзина порожня',
                'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard(), 'resize_keyboard' => true])
            ]);
            return;
        }

        $message = "🛒 <b>Ваша корзина:</b>\n\n";
        $total = 0;
        $inlineKeyboard = [];

        foreach ($member->cartItems as $item) {
            $product = $item->product;
            $itemTotal = $item->quantity * (float) $product->price;
            $total += $itemTotal;
            
            $message .= "📦 <b>{$product->name}</b>\n";
            $message .= "   Кількість: {$item->quantity} шт.\n";
            $message .= "   Ціна: {$product->price} грн × {$item->quantity} = {$itemTotal} грн\n\n";
            
            // Додаємо кнопки для управління кількістю
            $inlineKeyboard[] = [
                ['text' => '➖', 'callback_data' => 'decrease_quantity_' . $product->id],
                ['text' => $item->quantity, 'callback_data' => 'quantity_' . $product->id],
                ['text' => '➕', 'callback_data' => 'increase_quantity_' . $product->id],
                ['text' => '🗑', 'callback_data' => 'remove_from_cart_' . $product->id]
            ];
        }

        $message .= "💰 <b>Загальна сума: {$total} грн</b>";

        // Додаємо кнопки для загальних дій з корзиною
        $inlineKeyboard[] = [
            ['text' => '💳 Оформити замовлення', 'callback_data' => 'checkout_cart'],
            ['text' => '🗑 Очистити корзину', 'callback_data' => 'clear_cart']
        ];
        $inlineKeyboard[] = [
            ['text' => '⬅️ Назад до меню', 'callback_data' => 'back_to_menu']
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
                'text' => 'Корзина порожня'
            ]);
            return;
        }

        // Створюємо одне замовлення для всіх товарів корзини
        $totalAmount = 0;
        $orderItems = [];

        // Підраховуємо загальну суму та готуємо дані для товарів
        foreach ($member->cartItems as $cartItem) {
            $itemTotal = $cartItem->quantity * (float) $cartItem->product->price;
            $totalAmount += $itemTotal;
            
            $orderItems[] = [
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->product->price
            ];
        }

        // Створюємо замовлення
        $order = Order::create([
            'member_id' => $member->id,
            'status' => 'new',
            'total_amount' => $totalAmount,
            'source' => 'cart',
            'notes' => 'Замовлення з корзини'
        ]);

        // Створюємо товари замовлення
        foreach ($orderItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);
        }

        // Очищаємо корзину
        $member->cartItems()->delete();

        Telegram::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQueryId(),
            'text' => '✅ Замовлення оформлено!'
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Замовлення успішно оформлено!\n\n📋 Номер замовлення: {$order->order_number}\n💰 Загальна сума: {$order->formatted_total}\n\nМенеджер звʼяжеться з вами найближчим часом.",
            'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard(), 'resize_keyboard' => true])
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
            'text' => 'Корзина очищена'
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '🗑 Корзина очищена',
            'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard(), 'resize_keyboard' => true])
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

        // Перевіряємо чи товар вже є в корзині
        $cartItem = CartItem::where('member_id', $member->id)
                           ->where('product_id', $productId)
                           ->first();

        if ($cartItem) {
            // Якщо товар вже є, збільшуємо кількість
            $cartItem->increment('quantity');
        } else {
            // Якщо товару немає, створюємо новий запис
            CartItem::create([
                'member_id' => $member->id,
                'product_id' => $productId,
                'quantity' => 1
            ]);
        }

        Telegram::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQueryId(),
            'text' => "✅ {$product->name} додано в корзину"
        ]);
    }

    private function removeFromCart($chatId, $productId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        
        if ($member) {
            CartItem::where('member_id', $member->id)
                   ->where('product_id', $productId)
                   ->delete();
        }

        Telegram::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQueryId(),
            'text' => 'Товар видалено з корзини'
        ]);
        
        // Оновлюємо відображення корзини
        $this->updateCartMessage($chatId);
    }

    private function changeQuantity($chatId, $productId, $change)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        
        if (!$member) {
            return;
        }

        $cartItem = CartItem::where('member_id', $member->id)
                           ->where('product_id', $productId)
                           ->first();

        if (!$cartItem) {
            return;
        }

        $newQuantity = $cartItem->quantity + $change;
        
        if ($newQuantity <= 0) {
            // Якщо кількість стала 0 або менше, видаляємо товар
            $cartItem->delete();
            Telegram::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQueryId(),
                'text' => 'Товар видалено з корзини'
            ]);
        } else {
            // Оновлюємо кількість
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
                'text' => '🛒 Ваша корзина порожня',
                'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard(), 'resize_keyboard' => true])
            ]);
            return;
        }

        $message = "🛒 <b>Ваша корзина:</b>\n\n";
        $total = 0;
        $inlineKeyboard = [];

        foreach ($member->cartItems as $item) {
            $product = $item->product;
            $itemTotal = $item->quantity * (float) $product->price;
            $total += $itemTotal;
            
            $message .= "📦 <b>{$product->name}</b>\n";
            $message .= "   Кількість: {$item->quantity} шт.\n";
            $message .= "   Ціна: {$product->price} грн × {$item->quantity} = {$itemTotal} грн\n\n";
            
            // Додаємо кнопки для управління кількістю
            $inlineKeyboard[] = [
                ['text' => '➖', 'callback_data' => 'decrease_quantity_' . $product->id],
                ['text' => $item->quantity, 'callback_data' => 'quantity_' . $product->id],
                ['text' => '➕', 'callback_data' => 'increase_quantity_' . $product->id],
                ['text' => '🗑', 'callback_data' => 'remove_from_cart_' . $product->id]
            ];
        }

        $message .= "💰 <b>Загальна сума: {$total} грн</b>";

        // Додаємо кнопки для загальних дій з корзиною
        $inlineKeyboard[] = [
            ['text' => '💳 Оформити замовлення', 'callback_data' => 'checkout_cart'],
            ['text' => '🗑 Очистити корзину', 'callback_data' => 'clear_cart']
        ];
        $inlineKeyboard[] = [
            ['text' => '⬅️ Назад до меню', 'callback_data' => 'back_to_menu']
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
        $replacements = ['username' => ($member && $member->username) ? '@' . $member->username : ''];

        switch ($text) {
            case '📦 Каталог':
                $this->sendAnalogsMenu($chatId);
                break;
            case '🔥 Топ продаж':
                $products = Product::where('is_top_sales', true)->get();
                if ($products->count() > 0) {
                    foreach ($products as $index => $product) {
                        $caption = ($index+1) . ". <b>{$product->name}</b>\n";
                        $caption .= "💰 {$product->price} грн";
                        $localPath = public_path($product->image_url);
                        if (file_exists($localPath)) {
                            $photo = InputFile::create($localPath, basename($localPath));
                        } else {
                            $photo = $product->image_url;
                        }
                        $inlineKeyboard = [
                            [
                                ['text' => '🛒 Придбати зараз', 'callback_data' => 'buy_product_' . $product->id],
                                ['text' => '➕ Додати в корзину', 'callback_data' => 'add_to_cart_' . $product->id]
                            ]
                        ];
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
            case (preg_match('/^🛒 Корзина/', $text) ? true : false):
                $this->showCart($chatId);
                break;
            case '💳 Оформити замовлення':
                $this->checkoutCart($chatId);
                break;
            case '🗑 Очистити корзину':
                $this->clearCart($chatId);
                break;
            case '⬅️ Назад до меню':
                $this->sendMainMenu($chatId);
                break;
            case '📘 Як замовити':
                $messageText = $this->settings['howOrdering'] ?? 'Інформація відсутня.';
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Інструкція як замовити:</b> \n\n" . $this->settings['howOrdering'],
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard(), 'resize_keyboard' => true])
                ]);
                $this->sendMainMenu($chatId);
                break;
            case '💳 Оплата':
                $messageText = $this->settings['payment'] ?? 'Інформація відсутня.';
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Інформація про оплату:</b> \n\n" . $this->settings['payment'],
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard(), 'resize_keyboard' => true])
                ]);
                $this->sendMainMenu($chatId);
                break;
            case '⭐️ Відгуки':
                $messageText = $this->settings['reviews'] ?? 'Відгуки відсутні.';
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '<b>Відгуки наших клієнтів:</b> \n\n' . $this->settings['reviews'],
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMainMenuKeyboard(), 'resize_keyboard' => true])
                ]);
                $this->sendMainMenu($chatId);
                break;
            case '🌿 Moringa':
                if ($member) $member->update(['current_brand_id' => Brand::where('name', 'Like', '%Moringa%')->first()->id]);
                $this->sendMoringaMenu($chatId);
                break;
            case '🧪 Аналоги':
                if ($member) $member->update(['current_brand_id' => null]);
                $this->sendAnalogsMenu($chatId);
                break;
            case '⬅️ Назад':
                if ($member) $member->update(['current_brand_id' => null]);
                $this->sendMainMenu($chatId);
                break;
            case '📘 Про продукт':
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
                    'text' => 'Про категорію ' . $brand->name . ": \n\n" . $brand->description,
                    'reply_markup' => json_encode(['keyboard' => $this->getMoringaMenuKeyboard($brand), 'resize_keyboard' => true])
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
                    'text' => 'Прайс на категорію ' . $brand->name . ": \n\n" . $brand->price,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['keyboard' => $this->getMoringaMenuKeyboard($brand), 'resize_keyboard' => true])
                ]);
                break;
            case '🛍 Товари категорії':
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
                $this->sendAnalogsMenu($chatId);
                break;
        }
    }

    private function sendCatalogMenu($chatId)
    {
        $keyboard = [
            ['🌿 Moringa'],
            ['🧪 Аналоги'],
            ['⬅️ Назад'],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '.',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function sendMoringaMenu($chatId)
    {
        $brand = Brand::where('id', 1)->first();
        $keyboard = $this->getMoringaMenuKeyboard($brand);
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '☝',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function getMoringaMenuKeyboard($brand)
    {
        return [
            ['📘 Про продукт'],
            ['💰 Прайс'],
            ['🛍 Товари категорії'],
            ['⬅️ Назад'],
        ];
    }

    private function sendAnalogsMenu($chatId)
    {
        $brands = Brand::all();
        $keyboard = [];
        foreach ($brands as $brand) {
            $keyboard[] = [$brand->name];
        }
        $keyboard[] = ['⬅️ Назад'];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '☝',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function sendBrandAnalogMenu($chatId, $brandId)
    {
        $brand = Brand::find($brandId);
        $keyboard = [
            ['📘 Про продукт'],
            ['💰 Прайс'],
            ['🛍 Товари категорії'],
            ['⬅️ Назад'],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Категорія: ' . $brand->name,
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
    }

    private function sendBrandProductsMenu($chatId, $brandId)
    {
        $products = Product::where('brand_id', $brandId)->get();
        if ($products->count() > 0) {
            foreach ($products as $product) {
                $caption = "<b>{$product->name}</b>\n";
                $caption .= "{$product->description}\n";
                $caption .= "💰 {$product->price} грн";
                $inlineKeyboard = [
                    [
                        ['text' => '🛒 Придбати зараз', 'callback_data' => 'buy_product_' . $product->id],
                        ['text' => '➕ Додати в корзину', 'callback_data' => 'add_to_cart_' . $product->id]
                    ]
                ];

                if (!empty($product->image_url)) {
                    $localPath = public_path($product->image_url);
                    if (file_exists($localPath)) {
                        $photo = \Telegram\Bot\FileUpload\InputFile::create($localPath, basename($localPath));
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
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $caption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
                    ]);
                }
            }
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'У цієї категорії ще немає товарів.'
            ]);
        }
        // Повертаємо меню аналогів (ReplyKeyboardMarkup)
        $this->sendAnalogsMenu($chatId);
    }

    private function getMainMenuKeyboard()
    {
        return [
            ['📦 Каталог', '🔥 Топ продаж'],
            ['📘 Як замовити', '💳 Оплата'],
            ['⭐️ Відгуки'],
        ];
    }

    private function handleCallback($chatId, $data)
    {
        if (str_starts_with($data, 'buy_product_')) {
            $productId = (int)str_replace('buy_product_', '', $data);
            $member = Member::where('telegram_id', $chatId)->first();
            $product = Product::find($productId);
            
            if ($member && $product) {
                // Створюємо замовлення
                $order = Order::create([
                    'member_id' => $member->id,
                    'status' => 'new',
                    'total_amount' => $product->price,
                    'source' => 'direct',
                    'notes' => 'Пряме замовлення товару'
                ]);

                // Створюємо товар замовлення
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => $product->price
                ]);
            }
            
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "✅ Замовлення успішно створено!\n\n📋 Номер замовлення: {$order->order_number}\n💰 Сума: {$order->formatted_total}\n\nМенеджер звʼяжеться з вами найближчим часом."
            ]);
            $this->sendMainMenu($chatId);
        } elseif (str_starts_with($data, 'add_to_cart_')) {
            $productId = (int)str_replace('add_to_cart_', '', $data);
            $this->addToCart($chatId, $productId);
        } elseif (str_starts_with($data, 'remove_from_cart_')) {
            $productId = (int)str_replace('remove_from_cart_', '', $data);
            $this->removeFromCart($chatId, $productId);
        } elseif (str_starts_with($data, 'increase_quantity_')) {
            $productId = (int)str_replace('increase_quantity_', '', $data);
            $this->changeQuantity($chatId, $productId, 1);
        } elseif (str_starts_with($data, 'decrease_quantity_')) {
            $productId = (int)str_replace('decrease_quantity_', '', $data);
            $this->changeQuantity($chatId, $productId, -1);
        } elseif ($data === 'checkout_cart') {
            $this->checkoutCart($chatId);
        } elseif ($data === 'clear_cart') {
            $this->clearCart($chatId);
        } elseif ($data === 'back_to_menu') {
            $this->sendMainMenu($chatId);
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
}
