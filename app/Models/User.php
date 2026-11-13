<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasBlindIndexes;
use App\Concerns\HasRoles;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $school_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $phone_index
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'school_id', 'is_active'])]
#[Hidden(['password', 'phone_index', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasBlindIndexes, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Attributes that change on every login and would only add noise to the audit trail.
     *
     * @var list<string>
     */
    protected array $auditExclude = ['last_login_at', 'last_login_ip', 'phone_index'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone' => 'encrypted',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
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
     * @return array<string, string>
     */
    protected function blindIndexes(): array
    {
        return ['phone' => 'phone_index'];
    }

    protected function normalizeForBlindIndex(string $attribute, string $value): string
    {
        return $attribute === 'phone'
            ? (string) preg_replace('/\D+/', '', $value)
            : mb_strtolower(trim($value));
    }
}
