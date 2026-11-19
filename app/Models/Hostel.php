<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\HostelType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A boarding house with rooms, a warden and a monthly fee (in cents).
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property HostelType $type
 * @property int|null $warden_id
 * @property string|null $phone
 * @property string|null $address
 * @property int $monthly_fee
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'type', 'warden_id', 'phone', 'address', 'monthly_fee', 'is_active'])]
class Hostel extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HostelType::class,
            'monthly_fee' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function warden(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'warden_id');
    }

    /**
     * @return HasMany<HostelRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(HostelRoom::class)->orderBy('name');
    }
}
