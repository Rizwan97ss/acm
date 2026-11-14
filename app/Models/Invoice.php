<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bill for one student. Amounts are in cents; `paid` and `status` are maintained by BillingService.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int|null $invoice_batch_id
 * @property string $number
 * @property string $title
 * @property CarbonImmutable $issued_on
 * @property CarbonImmutable $due_on
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property int $paid
 * @property InvoiceStatus $status
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'student_id', 'academic_year_id', 'invoice_batch_id', 'number', 'title', 'issued_on', 'due_on', 'subtotal', 'discount', 'total', 'created_by'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'status' => InvoiceStatus::class,
            'voided_at' => 'datetime',
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
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->paid);
    }

    public function isOverdue(): bool
    {
        return $this->balance() > 0 && $this->status !== InvoiceStatus::Void && $this->due_on->isPast();
    }
}
