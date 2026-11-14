<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Live announcements addressed to the token owner. Drafts are never exposed over the API.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('announcements.view');

        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $announcements = Announcement::query()
            ->with('schoolClass:id,name')
            ->live()
            ->visibleTo($request->user())
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return AnnouncementResource::collection($announcements);
    }
}
