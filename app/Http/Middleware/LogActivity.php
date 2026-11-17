<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every controller action (web and API) to the `activity` log channel: who did
 * what, on which records, with what outcome and how long it took.
 *
 * Request bodies are never logged (they carry passwords, tokens and personal data);
 * only the names of the submitted fields are recorded.
 */
class LogActivity
{
    public const string CHANNEL = 'activity';

    /**
     * Background requests that would only add noise (the idle-timer keep-alive).
     */
    public const array QUIET_ROUTES = ['session.heartbeat'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $route = $request->route();
        $action = $route?->getActionName();

        if ($route === null || $action === null || $action === 'Closure' || in_array($route->getName(), self::QUIET_ROUTES, true)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $level = match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };

        Log::channel(self::CHANNEL)->log($level, sprintf('%s %s', $request->method(), $route->getName() ?? $route->uri()), [
            'user_id' => $request->user()?->id,
            'action' => str_replace('App\\Http\\Controllers\\', '', $action),
            'parameters' => $this->parameters($route->parameters()),
            'fields' => $request->isMethod('GET') ? [] : array_keys($request->except(['_token', '_method'])),
            'query' => $request->isMethod('GET') ? array_keys($request->query()) : [],
            'status' => $status,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'ip' => $request->ip(),
        ]);

        return $response;
    }

    /**
     * Route parameters as identifiers: bound models are reduced to their key.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, int|string|null>
     */
    private function parameters(array $parameters): array
    {
        $identifiers = [];

        foreach ($parameters as $name => $value) {
            $identifiers[$name] = match (true) {
                $value instanceof Model => $value->getKey(),
                is_scalar($value) => (string) $value,
                default => null,
            };
        }

        return $identifiers;
    }
}
