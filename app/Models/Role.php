<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Roles with a null school_id are platform-wide (system templates or global roles).
 * School roles may inherit every permission of their parent role.
 *
 * @property int $id
 * @property int|null $school_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'parent_id', 'name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Roles usable inside a school: platform-wide roles plus the school's own roles.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeAvailableTo(Builder $query, ?int $schoolId): void
    {
        $query->where(function (Builder $query) use ($schoolId): void {
            $query->whereNull('school_id');

            if ($schoolId !== null) {
                $query->orWhere('school_id', $schoolId);
            }
        });
    }

    public function isPlatformRole(): bool
    {
        return $this->school_id === null;
    }
}
