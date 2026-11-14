<?php

namespace App\Services\Analytics;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the stateless Python analytics service. Every request is signed with
 * HMAC-SHA256 over "{timestamp}.{METHOD}.{path}.{sha256(body)}" (mirrored in
 * analytics/app/security.py). The service never touches the database: callers send
 * aggregated, school-scoped datasets built by AnalyticsDatasets.
 *
 * Failures are logged and reported as null so pages degrade to an "offline" state
 * instead of erroring.
 */
class AnalyticsClient
{
    public function isConfigured(): bool
    {
        return strlen((string) config('services.analytics.secret')) >= 32 && filled(config('services.analytics.url'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function compute(string $path, array $payload): ?array
    {
        $response = $this->send($path, $payload);

        return $response?->json();
    }

    /**
     * A downloadable Plotly HTML report.
     *
     * @param  array<string, mixed>  $payload
     */
    public function report(array $payload): ?string
    {
        return $this->send('/v1/reports/html', $payload)?->body();
    }

    /**
     * @return array{X-Timestamp: string, X-Signature: string}
     */
    public function signatureHeaders(string $method, string $path, string $body, ?int $timestamp = null): array
    {
        $stamp = (string) ($timestamp ?? now()->getTimestamp());
        $message = $stamp.'.'.strtoupper($method).'.'.$path.'.'.hash('sha256', $body);

        return [
            'X-Timestamp' => $stamp,
            'X-Signature' => hash_hmac('sha256', $message, (string) config('services.analytics.secret')),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload): ?Response
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);

        try {
            return Http::baseUrl(rtrim((string) config('services.analytics.url'), '/'))
                ->timeout((int) config('services.analytics.timeout', 10))
                ->connectTimeout(3)
                ->withHeaders($this->signatureHeaders('POST', $path, $body))
                ->withBody($body, 'application/json')
                ->acceptJson()
                ->post($path)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Analytics service request failed.', ['path' => $path, 'error' => $exception->getMessage()]);

            return null;
        }
    }
}
