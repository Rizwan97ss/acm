<?php

namespace App\Http\Controllers\Records;

use App\Enums\Audience;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\SchoolClass;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared files. Stored on the private disk under random names, served only to people
 * in the document's audience, always as a download.
 */
class DocumentController extends Controller
{
    private const array ALLOWED = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg'];

    public function index(Request $request): Response
    {
        Gate::authorize('documents.view');

        $user = $request->user();
        $manages = $user->can('documents.create');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
        ]);

        $documents = $this->visibleQuery($request)
            ->with(['schoolClass:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('records/documents', [
            'documents' => [
                'data' => $documents->getCollection()->map(fn (Document $document): array => [
                    ...$document->only(['id', 'title', 'category', 'original_name', 'mime_type', 'size']),
                    'audience_label' => $document->audience->label(),
                    'school_class' => $document->schoolClass?->name,
                    'uploaded_at' => $document->created_at?->toIso8601String(),
                ])->values(),
                'meta' => [
                    'current_page' => $documents->currentPage(),
                    'from' => $documents->firstItem(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'to' => $documents->lastItem(),
                    'total' => $documents->total(),
                    'links' => $documents->linkCollection()->all(),
                ],
            ],
            'filters' => $filters,
            'categories' => Document::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'audiences' => Audience::options(),
            'classes' => $manages ? SchoolClass::query()->orderBy('name')->get(['id', 'name']) : [],
            'allowed' => self::ALLOWED,
            'can' => ['create' => $manages, 'delete' => $user->can('documents.delete')],
        ]);
    }

    public function store(Request $request, CurrentSchool $currentSchool, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('documents.create');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:60'],
            'audience' => ['required', Rule::enum(Audience::class)],
            'school_class_id' => [
                'nullable', 'integer', Rule::exists('school_classes', 'id')->where('school_id', $currentSchool->id()),
                Rule::prohibitedIf(in_array($request->input('audience'), [Audience::Everyone->value, Audience::Staff->value], true)),
            ],
            'file' => ['required', 'file', 'max:10240', 'extensions:'.implode(',', self::ALLOWED), 'mimes:'.implode(',', self::ALLOWED)],
        ]);

        $file = $request->file('file');
        $path = $file->store("documents/{$currentSchool->id()}", 'local');

        $document = Document::query()->create([
            'school_id' => $currentSchool->id(),
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'audience' => $validated['audience'],
            'school_class_id' => $validated['school_class_id'] ?? null,
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $audit->log('document.uploaded', $document);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded.')]);

        return back();
    }

    public function download(Request $request, int $document): StreamedResponse
    {
        Gate::authorize('documents.view');

        $record = $this->visibleQuery($request)->findOrFail($document);

        return Storage::disk('local')->download($record->path, $record->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('documents.delete');

        Storage::disk('local')->delete($document->path);
        $document->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted.')]);

        return back();
    }

    /**
     * @return Builder<Document>
     */
    private function visibleQuery(Request $request): Builder
    {
        $user = $request->user();

        return Document::query()->when(! $user->can('documents.create'), fn (Builder $query) => $query->visibleTo($user));
    }
}
