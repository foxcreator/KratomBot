<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Setting;
use App\Models\Product;
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
        $keyboard = [
            ['📦 Каталог', '🔥 Топ продаж'],
            ['📘 Як замовити', '💳 Оплата'],
            ['⭐️ Відгуки'],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text ?? '☝',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
        ]);
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
                                ['text' => '🛒 Придбати', 'callback_data' => 'buy_product_' . $product->id]
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
                        ['text' => '🛒 Придбати', 'callback_data' => 'buy_product_' . $product->id]
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
