<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The learning portal can be switched off per school (Settings → Learning).
 */
class EnsureLearningEnabled
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->current('lms.enabled')) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                abort(404);
            }

            Inertia::flash('toast', ['type' => 'info', 'message' => __('The learning portal is turned off for this school.')]);

            return to_route('dashboard');
        }

        return $next($request);
    }
}
