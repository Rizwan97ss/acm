<?php

namespace App\Services\Library;

use App\Enums\EmployeeStatus;
use App\Enums\StudentStatus;
use App\Models\Employee;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lending rules. The book row is locked while a copy is issued or returned so two desks
 * cannot lend the last copy at the same time.
 */
class LibraryService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Finds an active student (admission number) or employee (staff number).
     */
    public function resolveBorrower(string $code): Student|Employee
    {
        $code = trim($code);

        $student = Student::query()->where('admission_no', $code)->where('status', StudentStatus::Active->value)->first();

        if ($student !== null) {
            return $student;
        }

        return Employee::query()->where('employee_no', $code)->where('status', EmployeeStatus::Active->value)->first()
            ?? throw ValidationException::withMessages(['borrower' => __('No active student or staff member has that number.')]);
    }

    public function issue(LibraryBook $book, Student|Employee $borrower, User $actor, ?CarbonImmutable $dueOn = null): LibraryLoan
    {
        return DB::transaction(function () use ($book, $borrower, $actor, $dueOn): LibraryLoan {
            $book = LibraryBook::query()->lockForUpdate()->findOrFail($book->id);
            $column = $borrower instanceof Student ? 'student_id' : 'employee_id';
            $open = LibraryLoan::query()->open()->where($column, $borrower->id);

            if ($book->copies_available < 1) {
                throw ValidationException::withMessages(['library_book_id' => __('All copies of this book are on loan.')]);
            }

            if ((clone $open)->overdue()->exists()) {
                throw ValidationException::withMessages(['borrower' => __('This borrower has an overdue book. It must be returned first.')]);
            }

            $limit = (int) $this->settings->current('library.max_loans');

            if ((clone $open)->count() >= $limit) {
                throw ValidationException::withMessages(['borrower' => trans_choice('This borrower already has :count book on loan.|This borrower already has :count books on loan.', $limit, ['count' => $limit])]);
            }

            if ((clone $open)->where('library_book_id', $book->id)->exists()) {
                throw ValidationException::withMessages(['library_book_id' => __('This borrower already has a copy of this book.')]);
            }

            $today = CarbonImmutable::today();
            $loan = LibraryLoan::query()->create([
                'school_id' => $book->school_id,
                'library_book_id' => $book->id,
                $column => $borrower->id,
                'issued_on' => $today,
                'due_on' => $dueOn ?? $today->addDays((int) $this->settings->current('library.loan_days')),
                'issued_by' => $actor->id,
            ]);

            $book->decrement('copies_available');

            return $loan;
        });
    }

    /**
     * Records the return and any overdue fine (days late × the fine-per-day setting).
     */
    public function return(LibraryLoan $loan, User $actor): LibraryLoan
    {
        return DB::transaction(function () use ($loan, $actor): LibraryLoan {
            $book = LibraryBook::query()->lockForUpdate()->findOrFail($loan->library_book_id);
            $loan = LibraryLoan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->returned_on !== null) {
                throw ValidationException::withMessages(['loan' => __('This book has already been returned.')]);
            }

            $today = CarbonImmutable::today();
            $daysLate = max(0, (int) $loan->due_on->diffInDays($today, false));

            $loan->forceFill([
                'returned_on' => $today,
                'fine' => $daysLate * (int) $this->settings->current('library.fine_per_day'),
                'received_by' => $actor->id,
            ])->save();

            $book->increment('copies_available');

            return $loan;
        });
    }

    /**
     * Changes the number of copies owned, keeping copies on loan accounted for.
     */
    public function setCopies(LibraryBook $book, int $total): void
    {
        $onLoan = $book->loans()->open()->count();

        if ($total < $onLoan) {
            throw ValidationException::withMessages(['copies_total' => trans_choice(':count copy is on loan; the total cannot be lower.|:count copies are on loan; the total cannot be lower.', $onLoan, ['count' => $onLoan])]);
        }

        $book->forceFill(['copies_total' => $total, 'copies_available' => $total - $onLoan])->save();
    }
}
