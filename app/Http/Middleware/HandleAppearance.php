<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Handle an incoming request.
     *
     * The user's own choice (cookie) wins; otherwise the school/platform default theme applies.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $default = (string) $this->settings->current('branding.default_theme');

        View::share('defaultAppearance', $default);
        View::share('appearance', $request->cookie('appearance') ?? $default);

        return $next($request);
    }
}
