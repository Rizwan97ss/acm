<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a Content-Security-Policy on HTML pages. Scripts must come from this site or carry
 * the per-request nonce that Vite (and the inline theme script) are given, which stops
 * injected scripts from running. Inline styles stay allowed because React and server-side
 * rendering set style attributes.
 */
class AddContentSecurityPolicy
{
    public const string HEADER = 'Content-Security-Policy';

    public const string REPORT_ONLY_HEADER = 'Content-Security-Policy-Report-Only';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.csp.enabled') || Vite::isRunningHot()) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $header = config('security.csp.report_only') ? self::REPORT_ONLY_HEADER : self::HEADER;

        $response->headers->set($header, $this->policy($nonce));

        return $response;
    }

    private function policy(string $nonce): string
    {
        $imageSources = implode(' ', ["'self'", 'data:', 'blob:', ...config('security.csp.img_sources', [])]);

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src {$imageSources}",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
