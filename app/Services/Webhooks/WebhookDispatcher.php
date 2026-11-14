<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Records a delivery for every active endpoint subscribed to the event and queues it.
 * Jobs run after the surrounding transaction commits, so rolled-back changes never
 * leak out to integrations.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(int $schoolId, WebhookEvent $event, array $data): void
    {
        $endpoints = WebhookEndpoint::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $event === WebhookEvent::Ping || in_array($event->value, $endpoint->events, true));

        foreach ($endpoints as $endpoint) {
            $this->queue($endpoint, $event, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function queue(WebhookEndpoint $endpoint, WebhookEvent $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        $delivery = WebhookDelivery::query()->create([
            'uuid' => $uuid,
            'school_id' => $endpoint->school_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $event->value,
            'payload' => ['id' => $uuid, 'event' => $event->value, 'created_at' => now()->toIso8601String(), 'data' => $data],
        ]);

        DeliverWebhook::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }
}
