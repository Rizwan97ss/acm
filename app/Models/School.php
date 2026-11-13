<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $logo_path
 * @property bool $is_active
 * @property-read string|null $logo_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['parent_id', 'name', 'code', 'email', 'phone', 'address', 'logo_path', 'is_active'])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(School::class, 'parent_id');
    }

    /**
     * @return HasMany<School, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(School::class, 'parent_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @param  Builder<School>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null);
    }
}
