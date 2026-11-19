<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room in a hostel with a fixed number of beds.
 *
 * @property int $id
 * @property int $school_id
 * @property int $hostel_id
 * @property string $name
 * @property string|null $floor
 * @property int $capacity
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'hostel_id', 'name', 'floor', 'capacity', 'notes'])]
class HostelRoom extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * Current residents only.
     *
     * @return HasMany<HostelAllocation, $this>
     */
    public function residents(): HasMany
    {
        return $this->hasMany(HostelAllocation::class)->whereNull('ends_on');
    }
}
