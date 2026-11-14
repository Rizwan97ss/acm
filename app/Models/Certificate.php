<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use Carbon\CarbonImmutable;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A certificate for a student. Drafts can be edited; issuing (certificates.approve)
 * freezes the text. Anyone holding the printed copy can check it at /verify/{code}.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property CertificateType $type
 * @property string $serial_no
 * @property string $title
 * @property string $body
 * @property CertificateStatus $status
 * @property CarbonImmutable|null $issued_on
 * @property int|null $issued_by
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoke_reason
 * @property string $verification_code
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'student_id', 'type', 'serial_no', 'title', 'body', 'verification_code', 'created_by'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'status' => CertificateStatus::class,
            'issued_on' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
