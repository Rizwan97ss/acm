<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\TransportRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bus or van route with ordered stops and a seat capacity.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string|null $vehicle_no
 * @property string|null $driver_name
 * @property string|null $driver_phone
 * @property int $capacity
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'vehicle_no', 'driver_name', 'driver_phone', 'capacity', 'is_active'])]
class TransportRoute extends Model
{
    /** @use HasFactory<TransportRouteFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<TransportStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TransportStop::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<TransportAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TransportAssignment::class);
    }
}
