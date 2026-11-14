<?php

use App\Enums\SettingScope;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Services\Finance\LedgerService;
use App\Services\Library\LibraryService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function librarySchool(): array
{
    $setup = teachingSchool();
    $librarian = userWithPermissions(['library.view', 'library.create', 'library.edit', 'library.delete'], $setup['school']);
    $book = LibraryBook::factory()->create(['school_id' => $setup['school']->id, 'title' => 'Matilda', 'copies_total' => 1, 'copies_available' => 1]);

    return [...$setup, 'librarian' => $librarian, 'book' => $book];
}

function issueBook(array $setup, LibraryBook $book, string $borrower): TestResponse
{
    return test()->actingAs($setup['librarian'])->post(route('library.loans.store'), ['library_book_id' => $book->id, 'borrower' => $borrower]);
}

test('issuing a book uses the loan period and reduces available copies', function () {
    $setup = librarySchool();

    issueBook($setup, $setup['book'], $setup['student']->admission_no)->assertSessionHasNoErrors();

    $loan = LibraryLoan::query()->sole();
    expect($loan->student_id)->toBe($setup['student']->id)
        ->and($loan->due_on->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($setup['book']->fresh()->copies_available)->toBe(0);
});

test('staff can borrow by staff number', function () {
    $setup = librarySchool();
    $employee = Employee::factory()->create(['school_id' => $setup['school']->id]);

    issueBook($setup, $setup['book'], $employee->employee_no)->assertSessionHasNoErrors();

    expect(LibraryLoan::query()->sole()->employee_id)->toBe($employee->id);
});

test('the last copy cannot be lent twice', function () {
    $setup = librarySchool();
    $other = Student::factory()->create(['school_id' => $setup['school']->id]);

    issueBook($setup, $setup['book'], $setup['student']->admission_no);
    issueBook($setup, $setup['book'], $other->admission_no)->assertSessionHasErrors('library_book_id');

    expect(LibraryLoan::query()->count())->toBe(1);
});

test('borrowers over the limit or with overdue books are refused', function () {
    $setup = librarySchool();
    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['library.max_loans' => 1]);
    $second = LibraryBook::factory()->create(['school_id' => $setup['school']->id]);

    issueBook($setup, $setup['book'], $setup['student']->admission_no);
    issueBook($setup, $second, $setup['student']->admission_no)->assertSessionHasErrors('borrower');

    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['library.max_loans' => 5]);
    LibraryLoan::query()->update(['due_on' => now()->subDay()->toDateString()]);

    issueBook($setup, $second, $setup['student']->admission_no)->assertSessionHasErrors('borrower');
});

test('unknown or other-school borrowers are rejected', function () {
    $setup = librarySchool();
    $outsider = Student::factory()->create();

    issueBook($setup, $setup['book'], 'NOPE-1')->assertSessionHasErrors('borrower');
    issueBook($setup, $setup['book'], $outsider->admission_no)->assertSessionHasErrors('borrower');
});

test('late returns record a fine and free the copy', function () {
    $setup = librarySchool();
    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['library.fine_per_day' => 500]);
    issueBook($setup, $setup['book'], $setup['student']->admission_no);
    $loan = LibraryLoan::query()->sole();

    Date::setTestNow(now()->addDays(17));

    $this->actingAs($setup['librarian'])->post(route('library.loans.return', $loan))->assertSessionHasNoErrors();
    $this->actingAs($setup['librarian'])->post(route('library.loans.return', $loan))->assertSessionHasErrors('loan');

    expect($loan->fresh()->fine)->toBe(1500)
        ->and($setup['book']->fresh()->copies_available)->toBe(1);
});

test('a student\'s fine is billed once on a fee invoice credited to other income', function () {
    $setup = librarySchool();
    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['library.fine_per_day' => 500]);
    issueBook($setup, $setup['book'], $setup['student']->admission_no);
    $loan = LibraryLoan::query()->sole();
    Date::setTestNow(now()->addDays(17));

    $this->actingAs($setup['librarian'])->post(route('library.loans.return', $loan))->assertSessionHasNoErrors();
    app(LibraryService::class)->billFine($loan->fresh(), $setup['librarian']);

    $invoice = Invoice::query()->withoutGlobalScopes()->sole();
    expect($loan->fresh()->invoice_id)->toBe($invoice->id)
        ->and($invoice->student_id)->toBe($setup['student']->id)
        ->and($invoice->total)->toBe(1500)
        ->and($invoice->lines()->sole()->description)->toBe('Overdue: Matilda')
        ->and(JournalLine::query()->withoutGlobalScopes()->whereHas('account', fn ($query) => $query->where('code', LedgerService::OTHER_INCOME))->sum('credit'))->toBe(1500);
});

test('fines are not billed for staff or when billing is turned off', function () {
    $setup = librarySchool();
    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['library.fine_per_day' => 500, 'library.bill_fines' => false]);
    $second = LibraryBook::factory()->create(['school_id' => $setup['school']->id]);
    issueBook($setup, $setup['book'], $setup['student']->admission_no);
    issueBook($setup, $second, Employee::factory()->create(['school_id' => $setup['school']->id])->employee_no);
    Date::setTestNow(now()->addDays(17));

    foreach (LibraryLoan::query()->get() as $loan) {
        $this->actingAs($setup['librarian'])->post(route('library.loans.return', $loan))->assertSessionHasNoErrors();
    }
    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['library.bill_fines' => true]);
    app(LibraryService::class)->billFine(LibraryLoan::query()->whereNotNull('employee_id')->sole(), $setup['librarian']);

    expect(Invoice::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(LibraryLoan::query()->where('fine', '>', 0)->count())->toBe(2);
});

test('copies cannot be reduced below the number on loan', function () {
    $setup = librarySchool();
    issueBook($setup, $setup['book'], $setup['student']->admission_no);

    $this->actingAs($setup['librarian'])->put(route('library.books.update', $setup['book']), ['title' => 'Matilda', 'copies_total' => 0])
        ->assertSessionHasErrors('copies_total');
    $this->actingAs($setup['librarian'])->put(route('library.books.update', $setup['book']), ['title' => 'Matilda', 'copies_total' => 3])
        ->assertSessionHasNoErrors();

    expect($setup['book']->fresh())->copies_total->toBe(3)->copies_available->toBe(2);
});

test('readers see only their own loans and cannot issue', function () {
    $setup = librarySchool();
    $parent = userWithPermissions(['library.view'], $setup['school']);
    $setup['guardian']->update(['user_id' => $parent->id]);
    $other = Student::factory()->create(['school_id' => $setup['school']->id]);
    $second = LibraryBook::factory()->create(['school_id' => $setup['school']->id]);
    issueBook($setup, $setup['book'], $setup['student']->admission_no);
    issueBook($setup, $second, $other->admission_no);

    $this->actingAs($parent)->get(route('library.loans.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.book', 'Matilda')
            ->where('can.issue', false)
            ->where('books', []));

    $this->actingAs($parent)->post(route('library.loans.store'), ['library_book_id' => $second->id, 'borrower' => $setup['student']->admission_no])->assertForbidden();
});

test('books with loan history cannot be deleted', function () {
    $setup = librarySchool();
    issueBook($setup, $setup['book'], $setup['student']->admission_no);

    $this->actingAs($setup['librarian'])->delete(route('library.books.destroy', $setup['book']))->assertSessionHasErrors('book');
});
