<?php

namespace App\Services;

use App\Models\ProductOption;
use App\Models\SupplyItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Списує товар з партій FIFO і повертає supply_item_id => quantity
     */
    public function reserve(ProductOption $option, int $quantity): Collection
    {
        if ($option->current_quantity < $quantity) {
            throw ValidationException::withMessages([
                'product_option_id' => 'Недостатньо товару на складі.',
            ]);
        }

        $reserved = collect();
        $remaining = $quantity;

        $supplyItems = SupplyItem::where('product_option_id', $option->id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at')
            ->get();

        foreach ($supplyItems as $supplyItem) {
            $available = $supplyItem->remaining_quantity;

            $toReserve = min($available, $remaining);

            $reserved->push([
                'supply_item_id' => $supplyItem->id,
                'quantity' => $toReserve,
                'price' => $option->price, // або $supplyItem->purchase_price
            ]);

            // update DB
            $supplyItem->decrement('remaining_quantity', $toReserve);

            $remaining -= $toReserve;

            if ($remaining === 0) {
                break;
            }
        }

        // safety
        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'product_option_id' => 'Під час резерву щось пішло не так: товар закінчився.',
            ]);
        }

        // оновлюємо кількість та in_stock
        $option->decrement('current_quantity', $quantity);
        $option->update(['in_stock' => $option->current_quantity > 0]);

        return $reserved;
    }
}
