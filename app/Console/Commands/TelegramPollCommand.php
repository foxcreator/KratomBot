<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Poll Telegram updates locally and dispatch them to webhook';

    public function handle()
    {
        $this->info('Starting Telegram bot polling for local development...');
        $telegram = Telegram::bot();

        try {
            $telegram->removeWebhook();
            $this->info('Webhook removed successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to remove webhook: ' . $e->getMessage());
        }

        $offset = 0;
        $url = 'http://nginx/telegram/webhook';

        while (true) {
            try {
                $updates = $telegram->getUpdates(['offset' => $offset, 'timeout' => 30]);
                
                foreach ($updates as $update) {
                    $updateId = $update->update_id ?? (method_exists($update, 'getUpdateId') ? $update->getUpdateId() : ($update['update_id'] ?? null));
                    if (!$updateId) continue;
                    
                    $offset = $updateId + 1;
                    $this->info("Received update: {$updateId}");
                    
                    $response = \Illuminate\Support\Facades\Http::post($url, is_array($update) ? $update : $update->toArray());
                    if ($response->failed()) {
                        $this->error('Failed to forward update. Status: ' . $response->status() . ' Body: ' . $response->body());
                    } else {
                        $this->info('Successfully forwarded.');
                    }
                }
            } catch (\Exception $e) {
                $this->error('Polling error: ' . $e->getMessage());
                sleep(2);
            }
        }
    }
}
