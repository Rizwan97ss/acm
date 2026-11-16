<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brute-force protection for the Fortify endpoints that ship without a rate limiter:
 * reset-link requests (email flooding), password resets, password confirmation and
 * registration. Limits apply per IP and target account; the error appears on the form.
 */
class ThrottleAuthEndpoints
{
    /**
     * Route name => [attempts per minute, field that carries the error].
     *
     * @var array<string, array{0: int, 1: string}>
     */
    public const array LIMITS = [
        'password.email' => [5, 'email'],
        'password.update' => [5, 'email'],
        'password.confirm.store' => [5, 'password'],
        'register.store' => [5, 'email'],
    ];

    /**
     * One IP may try this many different accounts' worth of attempts per minute.
     */
    public const int PER_IP_MULTIPLIER = 4;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $request->isMethod('POST') || $routeName === null || ! isset(self::LIMITS[$routeName])) {
            return $next($request);
        }

        [$maxAttempts, $field] = self::LIMITS[$routeName];

        $limits = [
            $this->throttleKey($request, $routeName) => $maxAttempts,
            'auth-endpoint:'.$routeName.':ip:'.$request->ip() => $maxAttempts * self::PER_IP_MULTIPLIER,
        ];

        foreach ($limits as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $seconds = RateLimiter::availableIn($key);

                Log::warning('Auth endpoint throttled.', ['route' => $routeName, 'ip' => $request->ip(), 'user_id' => $request->user()?->id]);

                throw ValidationException::withMessages([
                    $field => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
                ]);
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }

    private function throttleKey(Request $request, string $routeName): string
    {
        $subject = $request->user()?->getAuthIdentifier() ?? Str::lower((string) $request->input('email'));

        return 'auth-endpoint:'.$routeName.':'.Str::transliterate($subject.'|'.$request->ip());
    }
}
