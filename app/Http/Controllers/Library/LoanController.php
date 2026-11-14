<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\User;
use App\Services\Library\LibraryService;
use App\Services\People\PortalScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Circulation desk. Librarians (library.create) see every loan; others see only their
 * own loans or their children's.
 */
class LoanController extends Controller
{
    public function __construct(
        private LibraryService $library,
        private PortalScope $portalScope,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('library.view');

        $user = $request->user();
        $manages = $user->can('library.create');
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,overdue,returned'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'open';

        $loans = LibraryLoan::query()
            ->with(['book:id,title,author', 'student:id,admission_no,first_name,last_name', 'employee:id,employee_no,first_name,last_name'])
            ->when(! $manages, fn (Builder $query) => $this->limitToOwn($query, $user))
            ->when($status === 'open', fn (Builder $query) => $query->open())
            ->when($status === 'overdue', fn (Builder $query) => $query->overdue())
            ->when($status === 'returned', fn (Builder $query) => $query->whereNotNull('returned_on'))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query
                    ->whereHas('book', fn (Builder $book) => $book->where('title', 'like', "%{$escaped}%"))
                    ->orWhereHas('student', fn (Builder $student) => $student->where('admission_no', $search)->orWhere('last_name', 'like', "%{$escaped}%"))
                    ->orWhereHas('employee', fn (Builder $employee) => $employee->where('employee_no', $search)->orWhere('last_name', 'like', "%{$escaped}%")));
            })
            ->orderBy($status === 'returned' ? 'returned_on' : 'due_on', $status === 'returned' ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('library/loans', [
            'loans' => [
                'data' => $loans->getCollection()->map(fn (LibraryLoan $loan): array => [
                    'id' => $loan->id,
                    'book' => $loan->book->title,
                    'author' => $loan->book->author,
                    'borrower' => $loan->student
                        ? "{$loan->student->first_name} {$loan->student->last_name}"
                        : trim("{$loan->employee?->first_name} {$loan->employee?->last_name}"),
                    'borrower_code' => $loan->student->admission_no ?? $loan->employee?->employee_no,
                    'borrower_type' => $loan->student_id ? 'student' : 'staff',
                    'issued_on' => $loan->issued_on->toDateString(),
                    'due_on' => $loan->due_on->toDateString(),
                    'returned_on' => $loan->returned_on?->toDateString(),
                    'overdue' => $loan->isOverdue(),
                    'fine' => $loan->fine,
                ])->values(),
                'meta' => [
                    'current_page' => $loans->currentPage(),
                    'from' => $loans->firstItem(),
                    'last_page' => $loans->lastPage(),
                    'per_page' => $loans->perPage(),
                    'to' => $loans->lastItem(),
                    'total' => $loans->total(),
                    'links' => $loans->linkCollection()->all(),
                ],
            ],
            'filters' => [...$filters, 'status' => $status],
            'overdueCount' => LibraryLoan::query()->overdue()->when(! $manages, fn (Builder $query) => $this->limitToOwn($query, $user))->count(),
            'books' => $manages
                ? LibraryBook::query()->where('copies_available', '>', 0)->orderBy('title')->limit(1000)->get(['id', 'title', 'author', 'copies_available'])
                : [],
            'can' => ['issue' => $manages, 'receive' => $user->can('library.edit')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('library.create');

        $validated = $request->validate([
            'library_book_id' => ['required', 'integer'],
            'borrower' => ['required', 'string', 'max:32'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $book = LibraryBook::query()->findOrFail((int) $validated['library_book_id']);
        $borrower = $this->library->resolveBorrower($validated['borrower']);
        $loan = $this->library->issue($book, $borrower, $request->user(), isset($validated['due_on']) ? CarbonImmutable::parse($validated['due_on']) : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issued “:title”, due :date.', ['title' => $book->title, 'date' => $loan->due_on->toDateString()])]);

        return back();
    }

    public function return(Request $request, LibraryLoan $loan): RedirectResponse
    {
        Gate::authorize('library.edit');

        $loan = $this->library->return($loan, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => $loan->fine > 0 ? __('Returned late. A fine was recorded.') : __('Book returned.')]);

        return back();
    }

    /**
     * @param  Builder<LibraryLoan>  $query
     */
    private function limitToOwn(Builder $query, User $user): void
    {
        $studentIds = $this->portalScope->studentIds($user);
        $employeeId = $user->employee()->value('id');

        $query->where(fn (Builder $query) => $query
            ->whereIn('student_id', $studentIds)
            ->when($employeeId !== null, fn (Builder $query) => $query->orWhere('employee_id', $employeeId)));
    }
}
