<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentSchool
{
    public const string SESSION_KEY = 'current_school_id';

    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $school = $user->school_id !== null
                ? $user->school
                : $this->switchedSchool($request);

            $this->currentSchool->set($school);
            Context::add('school_id', $school?->id);
        }

        return $next($request);
    }

    /**
     * Platform users may pick a school to work in; the selection lives in their session.
     */
    private function switchedSchool(Request $request): ?School
    {
        $schoolId = $request->session()->get(self::SESSION_KEY);

        if (! is_int($schoolId) || ! $request->user()->hasPermission('schools.view')) {
            return null;
        }

        return School::query()->find($schoolId);
    }
}
