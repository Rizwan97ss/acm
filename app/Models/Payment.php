<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received against an invoice. Payments are never deleted; a mistaken payment is reversed.
 *
 * @property int $id
 * @property int $school_id
 * @property int $invoice_id
 * @property int $student_id
 * @property string $receipt_no
 * @property int $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property CarbonImmutable $paid_on
 * @property string|null $notes
 * @property int|null $received_by
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property int|null $reversed_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'invoice_id', 'student_id', 'receipt_no', 'amount', 'method', 'reference', 'paid_on', 'notes', 'received_by'])]
class Payment extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'paid_on' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
