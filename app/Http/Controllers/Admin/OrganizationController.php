<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GoverningBody;
use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Services\People\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school's governing body: board of trustees, executive committee and advisers.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('organization.view');

        $members = OrganizationMember::query()->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('admin/organization', [
            'bodies' => collect(GoverningBody::cases())->map(fn (GoverningBody $body): array => [
                'value' => $body->value,
                'label' => $body->label(),
                'members' => $members->where('body', $body)->map(fn (OrganizationMember $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'position' => $member->position,
                    'body' => $member->body->value,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'bio' => $member->bio,
                    'term_starts_on' => $member->term_starts_on?->toDateString(),
                    'term_ends_on' => $member->term_ends_on?->toDateString(),
                    'sort_order' => $member->sort_order,
                    'is_active' => $member->is_active,
                    'photo_url' => $member->photo_path !== null ? route('people.photos.show', ['kind' => 'members', 'id' => $member->id, 'v' => substr(md5($member->photo_path), 0, 8)]) : null,
                ])->values(),
            ])->values(),
            'positions' => GoverningBody::suggestedPositions(),
            'can' => [
                'create' => $request->user()->hasPermission('organization.create'),
                'edit' => $request->user()->hasPermission('organization.edit'),
                'delete' => $request->user()->hasPermission('organization.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('organization.create');

        OrganizationMember::query()->create($this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return back();
    }

    public function update(Request $request, OrganizationMember $member): RedirectResponse
    {
        Gate::authorize('organization.edit');

        $member->update($this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return back();
    }

    public function destroy(OrganizationMember $member, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('organization.delete');

        $photos->delete($member->photo_path);
        $member->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'position' => ['required', 'string', 'max:60'],
            'body' => ['required', Rule::enum(GoverningBody::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'term_starts_on' => ['nullable', 'date'],
            'term_ends_on' => ['nullable', 'date', 'after_or_equal:term_starts_on'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]) + ['sort_order' => 0];
    }
}
