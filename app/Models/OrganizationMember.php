<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\GoverningBody;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A member of the school's governing body: trustee, president, secretary, treasurer …
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $position
 * @property GoverningBody $body
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $photo_path
 * @property string|null $bio
 * @property CarbonImmutable|null $term_starts_on
 * @property CarbonImmutable|null $term_ends_on
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'position', 'body', 'email', 'phone', 'bio', 'term_starts_on', 'term_ends_on', 'sort_order', 'is_active'])]
#[Hidden(['photo_path'])]
class OrganizationMember extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => GoverningBody::class,
            'phone' => 'encrypted',
            'term_starts_on' => 'date',
            'term_ends_on' => 'date',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
