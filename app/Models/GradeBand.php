<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $grade_scale_id
 * @property string $grade
 * @property string $min_percentage
 * @property string|null $grade_point
 * @property string|null $remark
 * @property bool $is_passing
 */
#[Fillable(['grade_scale_id', 'grade', 'min_percentage', 'grade_point', 'remark', 'is_passing'])]
class GradeBand extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'is_passing' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GradeScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class, 'grade_scale_id');
    }
}
