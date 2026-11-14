<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock changes only through append-only movements, so the history always explains
 * the current quantity.
 */
class InventoryService
{
    /**
     * In and out take a positive amount; adjust takes the counted quantity on hand.
     */
    public function record(InventoryItem $item, StockMovementType $type, int $amount, ?string $note, User $actor): InventoryMovement
    {
        return DB::transaction(function () use ($item, $type, $amount, $note, $actor): InventoryMovement {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            $change = match ($type) {
                StockMovementType::In => $amount,
                StockMovementType::Out => -$amount,
                StockMovementType::Adjust => $amount - $item->quantity,
            };

            if ($type !== StockMovementType::Adjust && $amount < 1) {
                throw ValidationException::withMessages(['quantity' => __('Enter a quantity of at least 1.')]);
            }

            if ($item->quantity + $change < 0) {
                throw ValidationException::withMessages(['quantity' => __('Only :count :unit in stock.', ['count' => $item->quantity, 'unit' => $item->unit])]);
            }

            if ($change === 0) {
                throw ValidationException::withMessages(['quantity' => __('The quantity is unchanged.')]);
            }

            $item->forceFill(['quantity' => $item->quantity + $change])->save();

            return InventoryMovement::query()->create([
                'school_id' => $item->school_id,
                'inventory_item_id' => $item->id,
                'type' => $type->value,
                'quantity' => $change,
                'balance_after' => $item->quantity,
                'note' => $note,
                'user_id' => $actor->id,
            ]);
        });
    }
}
