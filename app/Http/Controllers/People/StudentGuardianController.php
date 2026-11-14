<?php

namespace App\Http\Controllers\People;

use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\LinkGuardianRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\People\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentGuardianController extends Controller
{
    public function __construct(private GuardianService $guardians) {}

    /**
     * Link an existing guardian, or create one and link it.
     */
    public function store(LinkGuardianRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student): void {
            $guardian = $request->filled('guardian_id')
                ? Guardian::query()->findOrFail($request->integer('guardian_id'))
                : Guardian::query()->create([
                    ...$request->validated('guardian'),
                    'email' => $request->filled('guardian.email') ? Str::lower($request->string('guardian.email')->toString()) : null,
                    'school_id' => $student->school_id,
                ]);

            $this->guardians->link(
                $student,
                $guardian,
                GuardianRelationship::from($request->string('relationship')->toString()),
                $request->boolean('is_primary'),
                $request->boolean('is_emergency_contact'),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardian linked.')]);

        return to_route('people.students.show', $student);
    }

    public function destroy(Student $student, Guardian $guardian): RedirectResponse
    {
        Gate::authorize('update', $student);

        $this->guardians->unlink($student, $guardian);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardian unlinked.')]);

        return to_route('people.students.show', $student);
    }
}
