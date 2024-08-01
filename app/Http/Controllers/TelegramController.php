<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingsController;
use App\Jobs\DeletePromocodeMessage;
use App\Models\Member;
use App\Models\Promocode;
use App\Models\ScheduleDeleteMessages;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

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
                $member = Member::where('telegram_id', $chatId)->first();

                if ($data == 'check_subscription') {
                    $this->checkSubscription($chatId);
                }

                if ($data == 'activate_promocode' && !$member->promocode) {
                    $this->activatePromocode($chatId);
                } elseif($member->promocode) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Промокод вже активований'
                    ]);
                }
            } elseif ($update->isType('message')) {
                $chatId = $update->getMessage()->getChat()->getId();
                $text = $update->getMessage()->getText();

                if ($update->getMessage()->has('contact')) {
                    $contact = $update->getMessage()->getContact();
                    $this->handleContact($chatId, $contact);
                } else {
                    switch ($text) {
                        case '/start':
                            $this->startCommand($chatId);
                            break;
                    }
                }
            }
        }catch (Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    private function startCommand($chatId)
    {
        $keyboard = [
            [
                ['text' => $this->settings['phoneBtn'], 'request_contact' => true]
            ]
        ];

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $this->settings['helloMessage'],
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => true])
        ]);
    }

    private function handleContact($chatId, $contact)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        if ($member && isset($member->promoCode->code)) {
            if ($member->promoCode->is_used) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Ви вже використали свій промокод. \nЗалишайтеся з нами, незабаром будуть нові акції)",
                    'parse_mode' => 'HTML'
                ]);
            } else {
                $this->telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => InputFile::create($member->promoCode->barcode, $member->promoCode->code . '.png'),
                    'caption' => "Ви вже зареєстровані! \nВаш промокод: <b>{$member->promoCode->code}</b> \nПромокод буде дійсний протягом 10 хвилин після активації",
                    'parse_mode' => 'HTML'
                ]);
            }
        } else {
            Member::updateOrCreate(
                ['telegram_id' => $chatId],
                ['phone' => $contact->phone_number]
            );

            $this->offerSubscription($chatId);
        }
    }

    private function offerSubscription($chatId)
    {
        $channels = [];

        foreach (json_decode(Setting::where('key', 'channels')->first()->value) as $channel) {
            if (!empty($channel->name) && !empty($channel->url)) {
                $channels[] = [[
                    'text' => (string) $channel->name,
                    'url' => (string) $channel->url
                ]];
            }
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $this->settings['subscribe'],
            'reply_markup' => json_encode([
                'inline_keyboard' => $channels
            ]),
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "Будь ласка, підтвердіть виконання всіх умов:\n\nНатисніть кнопку \"Перевірити\" для автоматичної перевірки.\nЯкщо ви виконали всі кроки, ви отримаєте свій промокод! 🎉",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[['text' => 'Перевірити', 'callback_data' => 'check_subscription']]]
            ])
        ]);
    }

    private function checkSubscription($chatId)
    {
        $needCheck = true;
        foreach (json_decode($this->settings['channels']) as $channel) {
            if (!$channel->is_my) {
                $needCheck = false;
            }
        }

        $isSubscribed = $this->isUserSubscribed($chatId);

        if ($isSubscribed && !$needCheck) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $this->settings['activate'],
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[['text' => 'Активувати Промокод', 'callback_data' => 'activate_promocode']]]
                ])
            ]);

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $this->settings['whereUse'] ?? '',
                'parse_mode' => 'HTML'
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $this->settings['notSubscribe']
            ]);
        }
    }

    private function activatePromocode($chatId)
    {
        $member = Member::where('telegram_id', $chatId)->first();
        $promoCode = PromoCode::create(['member_id' => $member->id, 'code' => uniqid()]);

        $generator = new BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($promoCode->code, $generator::TYPE_CODE_128);

        // Сохранение штрихкода в файл
        $barcodePath = 'barcodes/' . $promoCode->code . '.png';
        Storage::put($barcodePath, $barcode);

        // Получение URL для сохраненного штрихкода
        $barcodeFullPath = Storage::path($barcodePath);

        // Отправка штрихкода в Telegram
        $response = $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create($barcodeFullPath, $promoCode->code . '.png'),
            'caption' => "Ваш промокод: <b>{$promoCode->code}</b> \nПромокод буде дійсний протягом 10 хвилин після активації",
            'parse_mode' => 'HTML',
        ]);

        $messageId = $response->getMessageId();

        ScheduleDeleteMessages::create([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'delete_at' => now()->addMinutes(10),
        ]);

        $promoCode->barcode = $barcodeFullPath;
        $promoCode->save();
    }

    private function isUserSubscribed($chatId)
    {
        try {
            foreach ($this->channelsUsername as $channel) {
                $response = $this->telegram->getChatMember([
                    'chat_id' => $channel,
                    'user_id' => $chatId
                ]);

                $status = $response->status;
                if (!in_array($status, ['member', 'administrator', 'creator'])) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::info("Exception caught: " . $e->getMessage());
            return false;
        }
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
