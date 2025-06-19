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
        $this->channelsUsername = $this->makeChannelName();
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
        $text = "Вітаємо, @$username!\n\nОберіть дію з меню нижче:";
        $this->sendMainMenu($chatId, $text);
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
        switch ($data) {
            case 'catalog':
                $this->sendCatalogMenu($chatId);
                break;
            case 'top_sales':
                $products = Product::take(5)->get();
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
            case 'how_to_order':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Інструкція як замовити: ...'
                ]);
                $this->sendMainMenu($chatId);
                break;
            case 'payment':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Інформація про оплату: ...'
                ]);
                $this->sendMainMenu($chatId);
                break;
            case 'reviews':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Відгуки наших клієнтів: ...'
                ]);
                $this->sendMainMenu($chatId);
                break;
            case 'catalog_moringa':
                $this->sendMoringaMenu($chatId);
                break;
            case 'catalog_analogs':
                $this->sendAnalogsMenu($chatId);
                break;
            case 'moringa_about':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Про продукт Морінга: ...'
                ]);
                $this->sendMoringaMenu($chatId);
                break;
            case 'moringa_price':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Прайс на Морінга: ...'
                ]);
                $this->sendMoringaMenu($chatId);
                break;
            case 'analogs_nps':
                $this->sendNpsMenu($chatId);
                break;
            case 'nps_about':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Про продукт НРС: ...'
                ]);
                $this->sendNpsMenu($chatId);
                break;
            case 'nps_price':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Прайс на НРС: ...'
                ]);
                $this->sendNpsMenu($chatId);
                break;
            case 'back_to_main':
                $this->sendMainMenu($chatId);
                break;
            case 'back_to_catalog':
                $this->sendCatalogMenu($chatId);
                break;
            case 'back_to_analogs':
                $this->sendAnalogsMenu($chatId);
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
        }
    }

    private function handleText($chatId, $text)
    {
        // Якщо користувач надсилає текст, просто показуємо головне меню
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
        $analogs = Brand::all();
        foreach ($analogs as $analog) {
            if ($analog->name == 'Moringa') {
                continue;
            }
            
            $keyboard[] = [
                ['text' => $analog->name, 'callback_data' => 'analogs_' . $analog->id . '_' . $analog->name]
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

    private function sendNpsMenu($chatId)
    {
        $keyboard = [
            [
                ['text' => '📘 Про продукт', 'callback_data' => 'nps_about'],
            ],
            [
                ['text' => '💰 Прайс', 'callback_data' => 'nps_price'],
            ],
            [
                ['text' => '⬅️ Назад', 'callback_data' => 'back_to_analogs'],
            ],
        ];
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '🌫 НРС:',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function makeChannelName()
    {
        $channels = json_decode(Setting::where('key', 'channels')->first()->value);
        $channelNames = [];
        foreach ($channels as $channel) {
            if ($channel->is_my) {
                $channelNames[] = str_replace("https://t.me/", "@", $channel->url);
            }
        }

        return $channelNames;
    }
}
