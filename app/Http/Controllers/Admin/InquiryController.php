<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The inbox for enquiries sent from the public landing page.
 */
class InquiryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('inquiries.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:unread,read'],
        ]);

        $inquiries = ContactInquiry::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%")
                    ->orWhere('organization', 'like', "%{$escaped}%"));
            })
            ->when(($filters['status'] ?? null) === 'unread', fn (Builder $query) => $query->whereNull('read_at'))
            ->when(($filters['status'] ?? null) === 'read', fn (Builder $query) => $query->whereNotNull('read_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/inquiries', [
            'inquiries' => [
                'data' => $inquiries->getCollection()->map(fn (ContactInquiry $inquiry): array => [
                    'id' => $inquiry->id,
                    'name' => $inquiry->name,
                    'email' => $inquiry->email,
                    'phone' => $inquiry->phone,
                    'organization' => $inquiry->organization,
                    'role' => $inquiry->role,
                    'school_size' => $inquiry->school_size !== null ? (ContactInquiry::SCHOOL_SIZES[$inquiry->school_size] ?? $inquiry->school_size) : null,
                    'interest' => ContactInquiry::INTERESTS[$inquiry->interest] ?? $inquiry->interest,
                    'message' => $inquiry->message,
                    'read' => $inquiry->read_at !== null,
                    'created_at' => $inquiry->created_at?->toIso8601String(),
                ])->values()->all(),
                'meta' => [
                    'current_page' => $inquiries->currentPage(),
                    'from' => $inquiries->firstItem(),
                    'last_page' => $inquiries->lastPage(),
                    'per_page' => $inquiries->perPage(),
                    'to' => $inquiries->lastItem(),
                    'total' => $inquiries->total(),
                    'links' => $inquiries->linkCollection()->all(),
                ],
            ],
            'filters' => $filters,
            'unread' => ContactInquiry::query()->whereNull('read_at')->count(),
            'can' => ['delete' => $request->user()->can('inquiries.delete')],
        ]);
    }

    /**
     * Mark an enquiry read or unread.
     */
    public function update(Request $request, ContactInquiry $inquiry): RedirectResponse
    {
        Gate::authorize('inquiries.view');

        $read = $request->validate(['read' => ['required', 'boolean']])['read'];
        $inquiry->forceFill(['read_at' => $read ? now() : null])->save();

        return back();
    }

    public function destroy(ContactInquiry $inquiry): RedirectResponse
    {
        Gate::authorize('inquiries.delete');

        $inquiry->delete();

        Log::info('Landing page enquiry deleted.', ['inquiry_id' => $inquiry->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enquiry deleted.')]);

        return back();
    }
}
