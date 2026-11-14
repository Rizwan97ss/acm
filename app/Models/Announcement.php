<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Concerns\TargetsAudience;
use App\Enums\Audience;
use Carbon\CarbonImmutable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A notice to an audience. Drafts are visible only to managers; publishing
 * (`announcements.approve`) makes it visible and notifies the audience.
 *
 * @property int $id
 * @property int $school_id
 * @property string $title
 * @property string $body
 * @property bool $is_pinned
 * @property CarbonImmutable|null $expires_on
 * @property CarbonImmutable|null $published_at
 * @property int|null $published_by
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'title', 'body', 'audience', 'school_class_id', 'is_pinned', 'expires_on', 'created_by'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use Auditable, BelongsToSchool, HasFactory, TargetsAudience;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => Audience::class,
            'is_pinned' => 'boolean',
            'expires_on' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Published and not yet expired.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()->toDateString()));
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
