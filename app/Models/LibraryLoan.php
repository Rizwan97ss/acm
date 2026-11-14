<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A copy of a book lent to a student or a member of staff (exactly one is set).
 *
 * @property int $id
 * @property int $school_id
 * @property int $library_book_id
 * @property int|null $student_id
 * @property int|null $employee_id
 * @property CarbonImmutable $issued_on
 * @property CarbonImmutable $due_on
 * @property CarbonImmutable|null $returned_on
 * @property int $fine
 * @property int|null $issued_by
 * @property int|null $received_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'library_book_id', 'student_id', 'employee_id', 'issued_on', 'due_on', 'issued_by'])]
class LibraryLoan extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'returned_on' => 'date',
            'fine' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('returned_on');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('returned_on')->whereDate('due_on', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->returned_on === null && $this->due_on->lt(CarbonImmutable::today());
    }

    /**
     * @return BelongsTo<LibraryBook, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
