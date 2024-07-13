<?php

namespace App\Http\Controllers;

use App\Models\Promocode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller
{
    protected $telegram;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function setWebhook()
    {
        // URL, на который нужно установить вебхук
        $webhookUrl = 'https://9568-93-127-12-107.ngrok-free.app/telegram/webhook';

        try {
            // Устанавливаем вебхук через SDK Telegram
            $response = $this->telegram->setWebhook(['url' => $webhookUrl]);

            if ($response) {
                return response()->json(['success' => true, 'message' => 'Webhook успешно установлен']);
            } else {
                return response()->json(['success' => false, 'message' => 'Не удалось установить Webhook: ' . $response->getDescription()]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Ошибка при установке вебхука: ' . $e->getMessage()]);
        }
    }
    public function webhook(Request $request)
    {

        try {

            $update = Telegram::getWebhookUpdates();
            $data = json_decode($update);

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
                        // Добавьте другие команды и их обработку
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
                ['text' => 'Предоставить номер телефона', 'request_contact' => true]
            ]
        ];

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Пожалуйста, предоставьте ваш номер телефона',
            'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => true])
        ]);
    }

    private function handleContact($chatId, $contact)
    {
        Log::info('Handling contact: ' . json_encode($contact));
        $user = User::updateOrCreate(
            ['telegram_id' => $chatId],
            ['phone' => $contact->phone_number]
        );

        // Отправляем сообщение о успешной регистрации и предложение подписаться на каналы
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "Спасибо за регистрацию, ваш номер телефона: {$contact->phone_number}. Подпишитесь на наш канал и получите актуальные новости.",
        ]);

        // Предложение подписаться на каналы
        $this->offerSubscription($chatId);
    }

    private function offerSubscription($chatId)
    {
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Подпишитесь на наши каналы:',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => 'Канал 1', 'url' => 'https://t.me/channel1']],
                    [['text' => 'Канал 2', 'url' => 'https://t.me/channel2']],
                ]
            ]),
        ]);
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'проверить',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[['text' => 'Проверить подписку', 'callback_data' => 'check_subscription']]]
            ])
        ]);
    }

    private function checkSubscription($chatId)
    {
        $isSubscribed = $this->isUserSubscribed($chatId);

        if ($isSubscribed) {
            $user = User::where('telegram_id', $chatId)->first();
            $promoCode = PromoCode::create(['user_id' => $user->id, 'code' => uniqid()]);
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Спасибо за подписку! Ваш промокод: {$promoCode->code}"
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Вы не подписались на наш канал. Пожалуйста, подпишитесь и попробуйте снова.'
            ]);
        }
    }

    private function isUserSubscribed($chatId)
    {
        // Логика проверки подписки на канал через Telegram API
        return true; // или false
    }

}
