<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Concerns\HasBlindIndexes;
use App\Concerns\HasFullName;
use Carbon\CarbonImmutable;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A parent or guardian. One guardian may be linked to several students (siblings).
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property-read string $full_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone_index
 * @property string|null $occupation
 * @property string|null $address
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['school_id', 'user_id', 'first_name', 'last_name', 'email', 'phone', 'occupation', 'address'])]
#[Hidden(['phone_index'])]
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use Auditable, BelongsToSchool, HasBlindIndexes, HasFactory, HasFullName, SoftDeletes;

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['phone_index'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'address' => 'encrypted',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function blindIndexes(): array
    {
        return ['phone' => 'phone_index'];
    }

    protected function normalizeForBlindIndex(string $attribute, string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot(['relationship', 'is_primary', 'is_emergency_contact']);
    }
}
