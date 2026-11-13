<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * School-owned modules need an active school. Platform users working across all
 * schools are asked to pick one first.
 */
class RequireSchoolContext
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->currentSchool->isGlobal()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Choose a school to work in first.')]);

            return to_route('admin.schools.index');
        }

        return $next($request);
    }
}
