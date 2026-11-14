<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Services\Library\LibraryService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalogue. Everyone with library.view can browse it; librarians maintain it.
 */
class BookController extends Controller
{
    public function __construct(private LibraryService $library) {}

    public function index(Request $request): Response
    {
        Gate::authorize('library.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
            'available' => ['nullable', 'boolean'],
        ]);

        $books = LibraryBook::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query
                    ->where('title', 'like', "%{$escaped}%")
                    ->orWhere('author', 'like', "%{$escaped}%")
                    ->orWhere('isbn', 'like', "%{$escaped}%"));
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($request->boolean('available'), fn (Builder $query) => $query->where('copies_available', '>', 0))
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('library/books', [
            'books' => [
                'data' => $books->getCollection()->map(fn (LibraryBook $book): array => $book->only(['id', 'title', 'author', 'isbn', 'publisher', 'category', 'shelf', 'copies_total', 'copies_available']))->values(),
                'meta' => [
                    'current_page' => $books->currentPage(),
                    'from' => $books->firstItem(),
                    'last_page' => $books->lastPage(),
                    'per_page' => $books->perPage(),
                    'to' => $books->lastItem(),
                    'total' => $books->total(),
                    'links' => $books->linkCollection()->all(),
                ],
            ],
            'filters' => [...$filters, 'available' => $request->boolean('available')],
            'categories' => LibraryBook::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'stats' => [
                'titles' => LibraryBook::query()->count(),
                'copies' => (int) LibraryBook::query()->sum('copies_total'),
                'onLoan' => (int) LibraryBook::query()->sum(DB::raw('copies_total - copies_available')),
            ],
            'can' => [
                'create' => $user->can('library.create'),
                'edit' => $user->can('library.edit'),
                'delete' => $user->can('library.delete'),
            ],
        ]);
    }

    public function store(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        Gate::authorize('library.create');

        $validated = $this->validateBook($request, $currentSchool, null);
        LibraryBook::query()->create([...$validated, 'copies_available' => $validated['copies_total']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Book added to the catalogue.')]);

        return back();
    }

    public function update(Request $request, LibraryBook $book, CurrentSchool $currentSchool): RedirectResponse
    {
        Gate::authorize('library.edit');

        $validated = $this->validateBook($request, $currentSchool, $book);

        DB::transaction(function () use ($book, $validated): void {
            $book->update(collect($validated)->except('copies_total')->all());
            $this->library->setCopies($book, (int) $validated['copies_total']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Book updated.')]);

        return back();
    }

    public function destroy(LibraryBook $book): RedirectResponse
    {
        Gate::authorize('library.delete');

        if ($book->loans()->exists()) {
            throw ValidationException::withMessages(['book' => __('This book has loan history and cannot be deleted. Set its copies to 0 instead.')]);
        }

        $book->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Book deleted.')]);

        return back();
    }

    /**
     * @return array{title: string, author: string|null, isbn: string|null, publisher: string|null, category: string|null, shelf: string|null, copies_total: int}
     */
    private function validateBook(Request $request, CurrentSchool $currentSchool, ?LibraryBook $book): array
    {
        if (is_string($request->input('isbn'))) {
            $request->merge(['isbn' => str_replace([' ', '-'], '', $request->input('isbn')) ?: null]);
        }

        /** @var array{title: string, author: string|null, isbn: string|null, publisher: string|null, category: string|null, shelf: string|null, copies_total: int} */
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:150'],
            'isbn' => ['nullable', 'string', 'regex:/^(\d{9}[\dXx]|\d{13})$/', Rule::unique('library_books')->where('school_id', $currentSchool->id())->ignore($book?->id)],
            'publisher' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:60'],
            'shelf' => ['nullable', 'string', 'max:30'],
            'copies_total' => ['required', 'integer', 'min:0', 'max:1000'],
        ], ['isbn.regex' => __('Enter a 10- or 13-digit ISBN.')]);
    }
}
