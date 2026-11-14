<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends one delivery. Receivers verify:
 *   X-Webhook-Signature = "sha256=" . HMAC-SHA256(secret, "{X-Webhook-Timestamp}.{raw body}")
 * and should reject timestamps older than a few minutes and repeated X-Webhook-Id values.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $deliveryId) {}

    public function tries(): int
    {
        return count((array) config('webhooks.backoff')) + 1;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_values(array_map(intval(...), (array) config('webhooks.backoff')));
    }

    public function handle(WebhookUrlGuard $guard): void
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->with(['endpoint' => fn ($query) => $query->withoutGlobalScopes()])->find($this->deliveryId);

        if ($delivery === null || $delivery->status === 'succeeded' || ! $delivery->endpoint?->is_active) {
            return;
        }

        $endpoint = $delivery->endpoint;
        $delivery->increment('attempts');

        ['problem' => $problem, 'pin' => $pin] = $guard->inspect($endpoint->url);

        if ($problem !== null) {
            $this->finish($delivery, 'failed', null, $problem);

            return;
        }

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->getTimestamp();

        try {
            $response = Http::timeout((int) config('webhooks.timeout', 10))
                ->withOptions($pin !== null ? ['curl' => [CURLOPT_RESOLVE => [$pin]]] : [])
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => 'SchoolERP-Webhooks/1.0',
                    'X-Webhook-Id' => $delivery->uuid,
                    'X-Webhook-Event' => $delivery->event,
                    'X-Webhook-Timestamp' => $timestamp,
                    'X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", $endpoint->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException $exception) {
            $this->retryOrFail($delivery, null, $exception->getMessage());

            return;
        }

        if ($response->successful()) {
            $this->finish($delivery, 'succeeded', $response->status(), null);

            return;
        }

        $this->retryOrFail($delivery, $response->status(), "HTTP {$response->status()}");
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->find($this->deliveryId);

        if ($delivery !== null && $delivery->status !== 'succeeded') {
            $this->finish($delivery, 'failed', $delivery->response_status, $exception?->getMessage() ?? $delivery->error);
        }
    }

    private function retryOrFail(WebhookDelivery $delivery, ?int $status, string $error): void
    {
        $delivery->forceFill(['response_status' => $status, 'error' => mb_substr($error, 0, 255)])->save();

        if ($this->attempts() < $this->tries()) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 60);

            return;
        }

        $this->finish($delivery, 'failed', $status, $error);
    }

    private function finish(WebhookDelivery $delivery, string $status, ?int $responseStatus, ?string $error): void
    {
        $delivery->forceFill([
            'status' => $status,
            'response_status' => $responseStatus,
            'error' => $error !== null ? mb_substr($error, 0, 255) : null,
            'delivered_at' => $status === 'succeeded' ? now() : null,
        ])->save();

        $endpoint = $delivery->endpoint()->withoutGlobalScopes()->first();

        if ($endpoint === null) {
            return;
        }

        if ($status === 'succeeded') {
            $endpoint->forceFill(['consecutive_failures' => 0])->save();

            return;
        }

        $failures = $endpoint->consecutive_failures + 1;
        $endpoint->forceFill([
            'consecutive_failures' => $failures,
            'is_active' => $failures < (int) config('webhooks.disable_after_failures', 20) && $endpoint->is_active,
        ])->save();

        if (! $endpoint->is_active && $failures >= (int) config('webhooks.disable_after_failures', 20)) {
            report(new RuntimeException("Webhook endpoint {$endpoint->id} disabled after {$failures} failed deliveries."));
        }
    }
}
