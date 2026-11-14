<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API counterpart of EnforceAccountSecurity: tokens of deactivated accounts, or of
 * accounts whose school is unavailable, stop working immediately. Platform users
 * without a school context cannot use school-owned endpoints.
 */
class EnsureApiAccountActive
{
    public function __construct(private CurrentSchool $currentSchool) {}

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

        abort_if(! $user->is_active || ($user->school_id !== null && ($school === null || ! $school->is_active)), 403, __('This account has been deactivated.'));
        abort_if($school === null, 403, __('This account is not linked to a school.'));

        return $next($request);
    }
}
