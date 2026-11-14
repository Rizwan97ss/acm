<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Concerns\TargetsAudience;
use App\Enums\Audience;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A shared file (policy, form, timetable PDF...). Files live on the private `local`
 * disk under generated names and are only served through DocumentController.
 *
 * @property int $id
 * @property int $school_id
 * @property string $title
 * @property string|null $category
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'title', 'category', 'audience', 'school_class_id', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
#[Hidden(['path'])]
class Document extends Model
{
    use Auditable, BelongsToSchool, TargetsAudience;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['audience' => Audience::class, 'size' => 'integer'];
    }
}
