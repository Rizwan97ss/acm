<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's stay in a hostel room. Open while ends_on is null.
 *
 * @property int $id
 * @property int $school_id
 * @property int $hostel_room_id
 * @property int $student_id
 * @property string|null $bed_label
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'hostel_room_id', 'student_id', 'bed_label', 'starts_on', 'ends_on', 'notes', 'created_by'])]
class HostelAllocation extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    /**
     * @return BelongsTo<HostelRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'hostel_room_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
