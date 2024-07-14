<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingsController;
use App\Models\Member;
use App\Models\Promocode;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller
{
    protected $telegram;
    protected $channelUsername;
    protected $settings;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $this->channelUsername = $this->makeChannelName();
        $this->settings = Setting::all()->pluck('value', 'key')->toArray();
    }

    public function setWebhook()
    {
        $url = 'https://3f35-93-127-13-38.ngrok-free.app/telegram/webhook'; // Укажите свой публичный URL, полученный от ngrok
        $response = $this->telegram->setWebhook(['url' => $url]);

        return response()->json($response);
    }
    public function webhook()
    {

        try {
            $update = Telegram::getWebhookUpdates();

            // Проверяем, что сообщение существует и является текстовым
            if ($update->isType('callback_query')) {
                $chatId = $update->getCallbackQuery()->getMessage()->getChat()->getId();
                $data = $update->getCallbackQuery()->getData();

                if ($data == 'check_subscription') {
                    $this->checkSubscription($chatId);
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

        // Дополнительные проверки и обработки
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
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Вы уже зарегестрированы.\n\n <b>Ваш промокод {$member->promoCode->code}</b>",
                'parse_mode' => 'HTML'
            ]);
        } else {
            Member::updateOrCreate(
                ['telegram_id' => $chatId],
                ['phone' => $contact->phone_number]
            );

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->settings['registered'],
            ]);

            $this->offerSubscription($chatId);
        }

    }

    private function offerSubscription($chatId)
    {
        $channels = [];

        foreach (json_decode(Setting::where('key', 'channels')->first()->value) as $channel) {
            $channels[] = ['text' => $channel->name, 'url' => $channel->url];
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $this->settings['subscribe'],
            'reply_markup' => json_encode([
                'inline_keyboard' => [$channels]
            ]),
        ]);
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Проверить',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[['text' => 'Проверить подписку', 'callback_data' => 'check_subscription']]]
            ])
        ]);
    }

    private function checkSubscription($chatId)
    {
        $isSubscribed = $this->isUserSubscribed($chatId);

        if ($isSubscribed) {
            $member = Member::where('telegram_id', $chatId)->first();
            $promoCode = PromoCode::create(['member_id' => $member->id, 'code' => uniqid()]);
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Спасибо за подписку! Ваш промокод: <b>{$promoCode->code}</b>",
                'parse_mode' => 'HTML'
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $this->settings['notSubscribe']
            ]);
        }
    }

    private function isUserSubscribed($chatId)
    {
        try {
            $response = $this->telegram->getChatMember([
                'chat_id' => $this->channelUsername,
                'user_id' => $chatId
            ]);

            $status = $response->status;

            return in_array($status, ['member', 'administrator', 'creator']);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return false;
        }
    }

    private function makeChannelName()
    {
        $channels = json_decode(Setting::where('key', 'channels')->first()->value);
        $channelName = '';
        foreach ($channels as $channel) {
            if ($channel->is_my) {
                $channelName = $channel->url;
                break;
            }
        }

        return str_replace("https://t.me/", "@", $channelName);

    }
}
