<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pick-up / drop-off point on a route. Scoped to the school through its route.
 *
 * @property int $id
 * @property int $transport_route_id
 * @property string $name
 * @property string|null $pickup_time
 * @property string|null $drop_time
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['transport_route_id', 'name', 'pickup_time', 'drop_time', 'position'])]
class TransportStop extends Model
{
    /**
     * @return BelongsTo<TransportRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }
}
