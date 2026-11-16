<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserTwoFactorController extends Controller
{
    public function __construct(private UserService $users) {}

    /**
     * Turn off two-factor sign-in for a user who has lost their authenticator and recovery codes.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resetTwoFactor', $user);

        if ($user->two_factor_secret === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':name does not have two-factor sign-in turned on.', ['name' => $user->name])]);

            return back();
        }

        $this->users->resetTwoFactor($request->user(), $user);

        Log::warning('Two-factor sign-in reset by an administrator.', ['user_id' => $user->id, 'actor_id' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Two-factor sign-in turned off for :name. They have been signed out everywhere.', ['name' => $user->name])]);

        return back();
    }
}
