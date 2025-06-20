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
        $url = env('APP_URL').'/telegram/webhook'; // Укажите свой публичный URL, полученный от ngrok
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
                    ['username' => $username, 'phone' => random_int(2, 10000000)]
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
        $text = !empty($this->settings['helloMessage']) ? $this->settings['helloMessage'] : "Вітаємо, @$username!\n\nОберіть дію з меню нижче:";
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
            [
                ['text' => '📦 Каталог', 'callback_data' => 'catalog'],
                ['text' => '🔥 Топ продаж', 'callback_data' => 'top_sales'],
            ],
            [
                ['text' => '📘 Як замовити', 'callback_data' => 'how_to_order'],
                ['text' => '💳 Оплата', 'callback_data' => 'payment'],
            ],
            [
                ['text' => '⭐️ Відгуки', 'callback_data' => 'reviews'],
            ],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text ?? 'Головне меню:',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function handleCallback($chatId, $data)
    {
        switch (true) {
            case $data === 'catalog':
                $this->sendCatalogMenu($chatId);
                break;
            case $data === 'top_sales':
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
                        $keyboard = [
                            [
                                ['text' => '🛒 Придбати', 'callback_data' => 'buy_product_' . $product->id]
                            ]
                        ];
                        Telegram::sendPhoto([
                            'chat_id' => $chatId,
                            'photo' => $photo,
                            'caption' => $caption,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
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
            case $data === 'how_to_order':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Інструкція як замовити:</b> \n\n" . $this->settings['howOrdering'],
                    'parse_mode' => 'HTML'
                ]);
                $this->sendMainMenu($chatId);
                break;
            case $data === 'payment':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Інформація про оплату:</b> \n\n" . $this->settings['payment'],
                    'parse_mode' => 'HTML'
                ]);
                $this->sendMainMenu($chatId);
                break;
            case $data === 'reviews':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '<b>Відгуки наших клієнтів:</b> '
                ]);
                $this->sendMainMenu($chatId);
                break;
            case $data === 'catalog_moringa':
                $this->sendMoringaMenu($chatId);
                break;
            case $data === 'catalog_analogs':
                $this->sendAnalogsMenu($chatId);
                break;
            case (preg_match('/^brand_menu_(\\d+)$/', $data, $matches) ? true : false):
                $brandId = $matches[1];
                $this->sendBrandAnalogMenu($chatId, $brandId);
                break;
            case (preg_match('/^brand_about_(\\d+)$/', $data, $matches) ? true : false):
                $brandId = $matches[1];
                $brand = Brand::find($brandId);
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Про бренд ' . $brand->name . ": \n\n" . $brand->description
                ]);
                $this->sendBrandAnalogMenu($chatId, $brandId);
                break;
            case (preg_match('/^brand_price_(\\d+)$/', $data, $matches) ? true : false):
                $brandId = $matches[1];
                $brand = Brand::find($brandId);
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Прайс на бренд ' . $brand->name . ": \n\n" . $brand->price,
                    'parse_mode' => 'HTML'
                ]);
                $this->sendBrandAnalogMenu($chatId, $brandId);
                break;
            case str_starts_with($data, 'buy_product_'):
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
                break;
            case (preg_match('/^brand_products_(\\d+)$/', $data, $matches) ? true : false):
                $brandId = $matches[1];
                $this->sendBrandProductsMenu($chatId, $brandId);
                break;
            case $data === 'moringa_products':
                $this->sendBrandProductsMenu($chatId, Brand::where('name', 'Moringa')->first()->id);
                break;
            default:
                $this->sendMainMenu($chatId);
                break;
        }
    }

    private function handleText($chatId, $text)
    {
        $this->sendMainMenu($chatId);
    }

    private function sendCatalogMenu($chatId)
    {
        $keyboard = [
            [
                ['text' => '🌿 Moringa', 'callback_data' => 'catalog_moringa'],
            ],
            [
                ['text' => '🧪 Аналоги', 'callback_data' => 'catalog_analogs'],
            ],
            [
                ['text' => '⬅️ Назад', 'callback_data' => 'back_to_main'],
            ],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Каталог:',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function sendMoringaMenu($chatId)
    {
        $keyboard = [
            [
                ['text' => '📘 Про продукт', 'callback_data' => 'moringa_about'],
            ],
            [
                ['text' => '💰 Прайс', 'callback_data' => 'moringa_price'],
            ],
            [
                ['text' => '🛍 Товари бренду', 'callback_data' => 'moringa_products'],
            ],
            [
                ['text' => '⬅️ Назад', 'callback_data' => 'back_to_catalog'],
            ],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '🌿 Moringa:',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function sendAnalogsMenu($chatId)
    {
        $brands = Brand::where('name', '!=', 'Moringa')->get();
        $keyboard = [];
        foreach ($brands as $brand) {
            $keyboard[] = [
                ['text' => $brand->name, 'callback_data' => 'brand_menu_' . $brand->id]
            ];
        }
        $keyboard[] = [
            ['text' => '⬅️ Назад', 'callback_data' => 'back_to_catalog'],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '🧪 Аналоги:',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function sendBrandAnalogMenu($chatId, $brandId)
    {
        $brand = Brand::find($brandId);
        $keyboard = [
            [
                ['text' => '📘 Про товар', 'callback_data' => 'brand_about_' . $brandId],
            ],
            [
                ['text' => '💰 Прайс', 'callback_data' => 'brand_price_' . $brandId],
            ],
            [
                ['text' => '🛍 Товари бренду', 'callback_data' => 'brand_products_' . $brandId],
            ],
            [
                ['text' => '⬅️ Назад', 'callback_data' => 'catalog_analogs'],
            ],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Бренд: ' . $brand->name,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
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
                $keyboard = [
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
                        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                    ]);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $caption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                    ]);
                }
            }
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'У цього бренду ще немає товарів.'
            ]);
        }
        // Повертаємо меню аналогів
        $this->sendAnalogsMenu($chatId);
    }
}
