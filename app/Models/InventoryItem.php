<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stocked item. `quantity` only changes through InventoryService movements.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $category
 * @property string $unit
 * @property string|null $location
 * @property int $quantity
 * @property int $reorder_level
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'sku', 'category', 'unit', 'location', 'reorder_level'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'reorder_level' => 'integer'];
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
