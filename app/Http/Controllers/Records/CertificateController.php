<?php

namespace App\Http\Controllers\Records;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Student;
use App\Services\People\PortalScope;
use App\Services\Records\CertificateService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Certificates: drafted by staff (certificates.create), issued or revoked by an approver,
 * printed with a verification link. Students and guardians see their own issued ones.
 */
class CertificateController extends Controller
{
    public function __construct(
        private CertificateService $certificates,
        private PortalScope $portalScope,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('certificates.view');

        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CertificateStatus::class)],
        ]);

        $certificates = $this->visibleQuery($request)
            ->with('student:id,admission_no,first_name,last_name')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query
                    ->where('serial_no', 'like', "%{$escaped}%")
                    ->orWhereHas('student', fn (Builder $student) => $student->where('admission_no', $search)->orWhere('last_name', 'like', "%{$escaped}%")->orWhere('first_name', 'like', "%{$escaped}%")));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('records/certificates', [
            'certificates' => [
                'data' => $certificates->getCollection()->map(fn (Certificate $certificate): array => [
                    'id' => $certificate->id,
                    'serial_no' => $certificate->serial_no,
                    'type_label' => $certificate->type->label(),
                    'title' => $certificate->title,
                    'body' => $certificate->body,
                    'status' => $certificate->status->value,
                    'status_label' => $certificate->status->label(),
                    'student' => "{$certificate->student->first_name} {$certificate->student->last_name}",
                    'admission_no' => $certificate->student->admission_no,
                    'issued_on' => $certificate->issued_on?->toDateString(),
                    'revoke_reason' => $certificate->revoke_reason,
                ])->values(),
                'meta' => [
                    'current_page' => $certificates->currentPage(),
                    'from' => $certificates->firstItem(),
                    'last_page' => $certificates->lastPage(),
                    'per_page' => $certificates->perPage(),
                    'to' => $certificates->lastItem(),
                    'total' => $certificates->total(),
                    'links' => $certificates->linkCollection()->all(),
                ],
            ],
            'filters' => $filters,
            'types' => CertificateType::options(),
            'statuses' => CertificateStatus::options(),
            'can' => [
                'create' => $user->can('certificates.create'),
                'edit' => $user->can('certificates.edit'),
                'approve' => $user->can('certificates.approve'),
                'delete' => $user->can('certificates.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('certificates.create');

        $validated = $request->validate([
            'admission_no' => ['required', 'string', 'max:32'],
            'type' => ['required', Rule::enum(CertificateType::class)],
        ]);

        $student = Student::query()->where('admission_no', trim($validated['admission_no']))->first()
            ?? throw ValidationException::withMessages(['admission_no' => __('No student has that admission number.')]);

        $this->certificates->draft($student, CertificateType::from($validated['type']), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft created. Review the wording, then issue it.')]);

        return back();
    }

    public function update(Request $request, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('certificates.edit');

        if ($certificate->status !== CertificateStatus::Draft) {
            throw ValidationException::withMessages(['certificate' => __('Issued certificates cannot be edited.')]);
        }

        $certificate->update($request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
        ]));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft updated.')]);

        return back();
    }

    public function issue(Request $request, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('certificates.approve');

        $this->certificates->issue($certificate, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate :serial issued.', ['serial' => $certificate->serial_no])]);

        return back();
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('certificates.approve');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->certificates->revoke($certificate, $validated['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate revoked.')]);

        return back();
    }

    public function destroy(Certificate $certificate): RedirectResponse
    {
        Gate::authorize('certificates.delete');

        if ($certificate->status !== CertificateStatus::Draft) {
            throw ValidationException::withMessages(['certificate' => __('Issued certificates are kept for verification; revoke instead.')]);
        }

        $certificate->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft deleted.')]);

        return back();
    }

    public function print(Request $request, int $certificate, CurrentSchool $currentSchool): Response
    {
        Gate::authorize('certificates.view');

        $record = $this->visibleQuery($request)->with('student:id,first_name,last_name,admission_no')->findOrFail($certificate);

        return Inertia::render('standalone/certificate', [
            'certificate' => [
                'serial_no' => $record->serial_no,
                'title' => $record->title,
                'body' => $record->body,
                'status' => $record->status->value,
                'issued_on' => $record->issued_on?->toDateString(),
                'verify_url' => route('certificates.verify', $record->verification_code),
            ],
            'school' => [
                'name' => $currentSchool->get()->name ?? config('app.name'),
                'logo_url' => $currentSchool->get()?->logo_url,
            ],
        ]);
    }

    /**
     * Public check of a printed certificate: shows only what is printed on it anyway.
     */
    public function verify(string $code): Response
    {
        $record = Certificate::query()
            ->withoutGlobalScopes()
            ->where('verification_code', $code)
            ->with(['student' => fn ($query) => $query->withoutGlobalScopes()->select(['id', 'first_name', 'last_name']), 'school:id,name'])
            ->first();

        return Inertia::render('standalone/verify-certificate', [
            'result' => $record === null || $record->status === CertificateStatus::Draft ? null : [
                'valid' => $record->status === CertificateStatus::Issued,
                'serial_no' => $record->serial_no,
                'title' => $record->title,
                'student' => "{$record->student->first_name} {$record->student->last_name}",
                'school' => $record->school->name,
                'issued_on' => $record->issued_on?->toDateString(),
                'revoked' => $record->status === CertificateStatus::Revoked,
            ],
        ]);
    }

    /**
     * Managers see everything; others only their own (or their children's) issued certificates.
     *
     * @return Builder<Certificate>
     */
    private function visibleQuery(Request $request): Builder
    {
        $user = $request->user();
        $manages = $user->can('certificates.create') || $user->can('certificates.approve');

        return Certificate::query()->when(! $manages, fn (Builder $query) => $query
            ->where('status', CertificateStatus::Issued->value)
            ->whereIn('student_id', $this->portalScope->studentIds($user)));
    }
}
