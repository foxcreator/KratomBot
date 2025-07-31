<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;

class FixImageUrls extends Command
{
    protected $signature = 'fix:image-urls';
    protected $description = 'Видалити префікс /storage/ з image_url у базі';

    public function handle()
    {
        $updated = 0;

        Product::where('image_url', 'like', '/storage/%')->get()->each(function ($item) use (&$updated) {
            $old = $item->image_url;
            $new = ltrim(preg_replace('#^/storage/#', '', $old), '/');

            if ($old !== $new) {
                $item->image_url = $new;
                $item->save();
                $updated++;
                $this->info("✓ Оновлено: {$old} → {$new}");
            }
        });

        $this->info("✅ Готово. Всього оновлено записів: {$updated}");

        return 0;
    }
}
