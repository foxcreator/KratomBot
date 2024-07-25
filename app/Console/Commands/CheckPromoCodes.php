<?php

namespace App\Console\Commands;

use App\Models\Promocode;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $promoCodes = PromoCode::where('created_at', '<', Carbon::now()->subMinutes(10))->get();

        foreach ($promoCodes as $promoCode) {
            $promoCode->update([
                'is_used' => true,
            ]);

            $this->info("Promo code {$promoCode->code} has been updated.");
        }

        return 0;
    }
}
