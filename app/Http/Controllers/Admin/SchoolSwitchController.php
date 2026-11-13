<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentSchool;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Lets platform users choose which school they are working in, or return to the all-schools view.
 */
class SchoolSwitchController extends Controller
{
    public function store(Request $request, School $school): RedirectResponse
    {
        Gate::authorize('switch', $school);

        $request->session()->put(ResolveCurrentSchool::SESSION_KEY, $school->id);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Now working in :school.', ['school' => $school->name])]);

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPlatformUser(), 403);

        $request->session()->forget(ResolveCurrentSchool::SESSION_KEY);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Now viewing all schools.')]);

        return to_route('dashboard');
    }
}
