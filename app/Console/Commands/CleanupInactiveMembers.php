<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;

class CleanupInactiveMembers extends Command
{
    protected $signature = 'members:cleanup
                            {--dry-run : Показати що буде видалено без фактичного видалення}
                            {--skip-check : Пропустити перевірку через Telegram, тільки видалити тих у кого немає bot_started_at}';

    protected $description = 'Soft-delete членів які ніколи не стартували бота або заблокували його';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $skipCheck = $this->option('skip-check');

        if ($dryRun) {
            $this->warn('Режим dry-run — нічого не буде видалено');
        }

        // --- Крок 1: юзери без bot_started_at ---
        $this->info('Крок 1: Видалення юзерів без bot_started_at...');

        $noStartQuery = Member::whereNull('bot_started_at');
        $noStartCount = $noStartQuery->count();

        $this->line("  Знайдено: {$noStartCount}");

        if (!$dryRun && $noStartCount > 0) {
            $noStartQuery->each(function (Member $member) {
                $member->delete();
            });
            $this->info("  Soft-deleted: {$noStartCount}");
        }

        // --- Крок 2: перевірка активних через sendChatAction ---
        if ($skipCheck) {
            $this->info('Крок 2 пропущено (--skip-check)');
            return self::SUCCESS;
        }

        $this->info('Крок 2: Перевірка активних юзерів через Telegram...');

        $telegram = new Api(config('telegram.bots.mybot.token'));

        $members = Member::whereNotNull('bot_started_at')->get(['id', 'telegram_id']);
        $total = $members->count();
        $blocked = 0;
        $errors = 0;

        $this->line("  Активних юзерів для перевірки: {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($members as $member) {
            try {
                $telegram->sendChatAction([
                    'chat_id' => $member->telegram_id,
                    'action'  => 'typing',
                ]);
                // невелика пауза щоб не флудити API
                usleep(50000); // 50ms
            } catch (TelegramResponseException $e) {
                $errorDesc = $e->getMessage();

                if (str_contains($errorDesc, 'Forbidden') || str_contains($errorDesc, 'bot was blocked') || str_contains($errorDesc, 'user is deactivated')) {
                    $blocked++;
                    if (!$dryRun) {
                        $member->bot_blocked_at = now();
                        $member->save();
                        $member->delete();
                    }
                    Log::info('[CleanupInactiveMembers] Заблокований', [
                        'telegram_id' => $member->telegram_id,
                        'error' => $errorDesc,
                    ]);
                } else {
                    // Інша помилка (flood, timeout тощо) — не чіпаємо
                    $errors++;
                    Log::warning('[CleanupInactiveMembers] Інша помилка', [
                        'telegram_id' => $member->telegram_id,
                        'error' => $errorDesc,
                    ]);
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('[CleanupInactiveMembers] Виняток', [
                    'telegram_id' => $member->telegram_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("  Заблокованих (soft-deleted): {$blocked}");
        if ($errors > 0) {
            $this->warn("  Помилок (не чіпали): {$errors}");
        }
        $this->info('Готово.');

        return self::SUCCESS;
    }
}
