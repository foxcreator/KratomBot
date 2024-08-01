<?php

namespace App\Console\Commands;

use App\Models\Promocode;
use App\Models\ScheduleDeleteMessages;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Telegram\Bot\Api;
class CheckPromoCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if 10 minutes have passed since the promo code was created and update the record if necessary';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $messages = ScheduleDeleteMessages::where('delete_at', '<', now())->get();
        $promoCodes = PromoCode::where('created_at', '<', Carbon::now()->subMinutes(1))->get();

        foreach ($promoCodes as $promoCode) {
            $promoCode->update([
                'is_used' => true,
            ]);

            $this->info("Promo code {$promoCode->code} has been updated.");
        }

        foreach ($messages as $message) {
            $telegram->deleteMessage([
                'chat_id' => $message->chat_id,
                'message_id' => $message->message_id,
            ]);

            $telegram->sendMessage([
                'chat_id' => $message->chat_id,
                'text' => 'Термін дії промокоду минув',
            ]);
            $message->delete();

        }



        return 0;
    }
}
