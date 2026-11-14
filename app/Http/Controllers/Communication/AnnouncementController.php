<?php

namespace App\Http\Controllers\Communication;

use App\Enums\Audience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Services\Communication\AnnouncementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Announcements board. Managers (create/approve) see drafts and everything published;
 * everyone else sees live announcements addressed to them.
 */
class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $announcements) {}

    public function index(Request $request): Response
    {
        Gate::authorize('announcements.view');

        $user = $request->user();
        $manages = $user->can('announcements.create') || $user->can('announcements.approve');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'highlight' => ['nullable', 'integer'],
        ]);

        $announcements = Announcement::query()
            ->with(['schoolClass:id,name', 'author:id,name'])
            ->when(! $manages, fn (Builder $query) => $query->live()->visibleTo($user))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query->where('title', 'like', "%{$escaped}%")->orWhere('body', 'like', "%{$escaped}%"));
            })
            ->orderByDesc('is_pinned')
            ->orderByRaw('published_at is null desc')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('communication/announcements/index', [
            'announcements' => [
                'data' => $announcements->getCollection()->map(fn (Announcement $announcement): array => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'audience' => $announcement->audience->value,
                    'audience_label' => $announcement->audience->label(),
                    'school_class_id' => $announcement->school_class_id,
                    'school_class' => $announcement->schoolClass?->name,
                    'is_pinned' => $announcement->is_pinned,
                    'expires_on' => $announcement->expires_on?->toDateString(),
                    'published_at' => $announcement->published_at?->toIso8601String(),
                    'author' => $announcement->author?->name,
                ])->values(),
                'meta' => [
                    'current_page' => $announcements->currentPage(),
                    'from' => $announcements->firstItem(),
                    'last_page' => $announcements->lastPage(),
                    'per_page' => $announcements->perPage(),
                    'to' => $announcements->lastItem(),
                    'total' => $announcements->total(),
                    'links' => $announcements->linkCollection()->all(),
                ],
            ],
            'filters' => $filters,
            'audiences' => Audience::options(),
            'classes' => $manages ? SchoolClass::query()->orderBy('name')->get(['id', 'name']) : [],
            'can' => [
                'create' => $user->can('announcements.create'),
                'edit' => $user->can('announcements.edit'),
                'delete' => $user->can('announcements.delete'),
                'approve' => $user->can('announcements.approve'),
            ],
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $announcement = Announcement::query()->create([...$request->payload(), 'created_by' => $request->user()->id]);

        if ($request->boolean('publish') && $request->user()->can('announcements.approve')) {
            return $this->published($announcement, $request);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement saved as a draft.')]);

        return to_route('communication.announcements.index');
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($request->payload());

        if ($request->boolean('publish') && ! $announcement->isPublished() && $request->user()->can('announcements.approve')) {
            return $this->published($announcement, $request);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement updated.')]);

        return to_route('communication.announcements.index');
    }

    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('announcements.approve');
        abort_if($announcement->isPublished(), 409);

        return $this->published($announcement, $request);
    }

    public function unpublish(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('announcements.approve');

        $this->announcements->unpublish($announcement);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement moved back to drafts.')]);

        return to_route('communication.announcements.index');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('announcements.delete');

        $announcement->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement deleted.')]);

        return to_route('communication.announcements.index');
    }

    private function published(Announcement $announcement, Request $request): RedirectResponse
    {
        $notified = $this->announcements->publish($announcement, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('Published and sent to :count person.|Published and sent to :count people.', $notified, ['count' => $notified])]);

        return to_route('communication.announcements.index');
    }
}
