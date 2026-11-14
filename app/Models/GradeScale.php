<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps percentages to grades. Each band applies from its minimum percentage up to the next band.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property bool $is_default
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'is_default'])]
class GradeScale extends Model
{
    /** @use HasFactory<GradeScaleFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Bands from the highest minimum down.
     *
     * @return HasMany<GradeBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderByDesc('min_percentage');
    }

    /**
     * The band a percentage falls in, using already-loaded bands.
     */
    public function bandFor(float $percentage): ?GradeBand
    {
        return $this->bands->first(fn (GradeBand $band): bool => $percentage >= (float) $band->min_percentage);
    }
}
