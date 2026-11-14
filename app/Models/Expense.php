<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money spent. Submitted as pending; posting to the ledger happens on approval.
 *
 * @property int $id
 * @property int $school_id
 * @property int $expense_category_id
 * @property string $number
 * @property string $payee
 * @property string $description
 * @property int $amount
 * @property CarbonImmutable $spent_on
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property ExpenseStatus $status
 * @property int|null $submitted_by
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'expense_category_id', 'number', 'payee', 'description', 'amount', 'spent_on', 'method', 'reference', 'submitted_by'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'spent_on' => 'date',
            'method' => PaymentMethod::class,
            'status' => ExpenseStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
