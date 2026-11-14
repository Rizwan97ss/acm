<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FeeConcession;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * A student's standing fee concession, applied to invoices generated afterwards.
 */
class ConcessionController extends Controller
{
    public function update(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('fees.edit');

        $validated = $request->validate([
            'percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        FeeConcession::query()->updateOrCreate(
            ['student_id' => $student->id],
            ['school_id' => $student->school_id, 'percent' => $validated['percent'], 'reason' => $validated['reason']],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Concession saved. It applies to invoices generated from now on.')]);

        return to_route('people.students.show', $student);
    }

    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('fees.edit');

        FeeConcession::query()->where('student_id', $student->id)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Concession removed.')]);

        return to_route('people.students.show', $student);
    }
}
