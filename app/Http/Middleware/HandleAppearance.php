<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * The only values ever written into the page (the cookie is unencrypted and user-controlled).
     */
    public const array APPEARANCES = ['light', 'dark', 'system'];

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
        $chosen = $request->cookie('appearance');

        View::share('appearance', in_array($chosen, self::APPEARANCES, true) ? $chosen : $default);

        return $next($request);
    }
}
