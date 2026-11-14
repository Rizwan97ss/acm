<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only stock movement: in (received), out (issued) or adjust (stock count).
 *
 * @property int $id
 * @property int $school_id
 * @property int $inventory_item_id
 * @property string $type
 * @property int $quantity
 * @property int $balance_after
 * @property string|null $note
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'inventory_item_id', 'type', 'quantity', 'balance_after', 'note', 'user_id'])]
class InventoryMovement extends Model
{
    use BelongsToSchool;

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
