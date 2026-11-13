<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces account-level security policies on every authenticated request:
 * deactivated accounts or schools, idle-session timeout and mandatory 2FA.
 */
class EnforceAccountSecurity
{
    private const string LAST_ACTIVITY_KEY = 'security.last_activity_at';

    /**
     * Routes a user without 2FA may still reach while enrolment is mandatory.
     *
     * @var list<string>
     */
    private const array TWO_FACTOR_EXEMPT_ROUTES = [
        'security.edit', 'logout', 'password.confirm', 'password.confirmation', 'password.confirm.store',
        'two-factor.*', 'user-password.update', 'verification.*', 'passkey.*',
    ];

    public function __construct(
        private SettingsService $settings,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $school = $this->currentSchool->get();
        $schoolUnavailable = $user->school_id !== null && ($school === null || ! $school->is_active);

        if (! $user->is_active || $schoolUnavailable) {
            return $this->logout($request, __('Your account has been deactivated. Contact your administrator.'));
        }

        if ($this->idleTimedOut($request)) {
            return $this->logout($request, __('You were signed out after a period of inactivity.'));
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, now()->getTimestamp());

        if ($this->settings->get('security.require_two_factor')
            && $user->two_factor_confirmed_at === null
            && ! $request->routeIs(...self::TWO_FACTOR_EXEMPT_ROUTES)) {
            return to_route('security.edit')->with('status', 'two-factor-required');
        }

        return $next($request);
    }

    private function idleTimedOut(Request $request): bool
    {
        $minutes = (int) $this->settings->get('security.session_idle_timeout');
        $lastActivity = $request->session()->get(self::LAST_ACTIVITY_KEY);

        return $minutes > 0
            && $lastActivity !== null
            && now()->getTimestamp() - (int) $lastActivity > $minutes * 60;
    }

    private function logout(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', $message);
    }
}
