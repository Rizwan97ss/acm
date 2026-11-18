<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\MaterialKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file or link attached to a lesson. Files live on the private LMS disk and are only
 * served through signed, access-checked links.
 *
 * @property int $id
 * @property int $school_id
 * @property int $lesson_id
 * @property MaterialKind $kind
 * @property string $title
 * @property string|null $description
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $url
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_downloadable
 * @property int $position
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'lesson_id', 'kind', 'title', 'description', 'disk', 'path', 'url', 'original_name', 'mime_type', 'size', 'is_downloadable', 'position', 'uploaded_by'])]
#[Hidden(['path', 'disk'])]
class LessonMaterial extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MaterialKind::class,
            'size' => 'integer',
            'is_downloadable' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
