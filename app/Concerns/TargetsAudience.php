<?php

namespace App\Concerns;

use App\Enums\Audience;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Communication\AudienceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared by announcements and events: an `audience` plus an optional `school_class_id`.
 *
 * @property Audience $audience
 * @property int|null $school_class_id
 */
trait TargetsAudience
{
    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Records addressed to the user. Staff see everyone/staff items, students and guardians
     * see everyone items plus their own audience, limited to their children's classes.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $viewer = app(AudienceResolver::class)->viewerProfile($user);

        $query->where(function (Builder $query) use ($viewer): void {
            $query->where('audience', Audience::Everyone->value);

            foreach ($viewer['audiences'] as $audience) {
                $query->orWhere(function (Builder $query) use ($audience, $viewer): void {
                    $query->where('audience', $audience->value);

                    if ($audience !== Audience::Staff) {
                        $query->where(fn (Builder $query) => $query->whereNull('school_class_id')->orWhereIn('school_class_id', $viewer['class_ids']));
                    }
                });
            }
        });
    }
}
