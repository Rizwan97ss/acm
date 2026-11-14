<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\People\PortalAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Invites students and guardians to the portal by email.
 */
class PortalAccountController extends Controller
{
    public function __construct(private PortalAccountService $accounts) {}

    public function student(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('invite', $student);

        $this->accounts->invite($request->user(), $student);

        return $this->invited($student->email, to_route('people.students.show', $student));
    }

    public function guardian(Request $request, Guardian $guardian): RedirectResponse
    {
        Gate::authorize('invite', $guardian);

        $this->accounts->invite($request->user(), $guardian);

        return $this->invited($guardian->email, back());
    }

    private function invited(?string $email, RedirectResponse $redirect): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $email])]);

        return $redirect;
    }
}
