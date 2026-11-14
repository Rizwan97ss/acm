<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\CurrentSchool;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Outgoing webhook endpoints for the current school. Signing secrets are shown once,
 * when created or rotated.
 */
class WebhookController extends Controller
{
    public function __construct(
        private WebhookUrlGuard $guard,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('integrations.view');

        $user = $request->user();

        return Inertia::render('admin/webhooks', [
            'endpoints' => WebhookEndpoint::query()->latest('id')->get()->map(fn (WebhookEndpoint $endpoint): array => [
                ...$endpoint->only(['id', 'url', 'description', 'events', 'is_active', 'consecutive_failures']),
                'created_at' => $endpoint->created_at?->toIso8601String(),
            ])->values(),
            'deliveries' => WebhookDelivery::query()
                ->with('endpoint:id,url')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (WebhookDelivery $delivery): array => [
                    'id' => $delivery->id,
                    'uuid' => $delivery->uuid,
                    'event' => $delivery->event,
                    'url' => $delivery->endpoint->url ?? '—',
                    'status' => $delivery->status,
                    'attempts' => $delivery->attempts,
                    'response_status' => $delivery->response_status,
                    'error' => $delivery->error,
                    'created_at' => $delivery->created_at?->toIso8601String(),
                ])->values(),
            'events' => WebhookEvent::subscribable(),
            'can' => [
                'create' => $user->can('integrations.create'),
                'edit' => $user->can('integrations.edit'),
                'delete' => $user->can('integrations.delete'),
            ],
        ]);
    }

    public function store(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        Gate::authorize('integrations.create');

        $validated = $this->validateEndpoint($request);
        $secret = 'whsec_'.Str::random(40);

        $endpoint = WebhookEndpoint::query()->create([
            ...$validated,
            'school_id' => $currentSchool->id(),
            'secret' => $secret,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log('webhook.created', $endpoint, [], ['url' => $endpoint->url]);
        Inertia::flash('secret', ['endpoint_id' => $endpoint->id, 'value' => $secret]);

        return back();
    }

    public function update(Request $request, WebhookEndpoint $webhook): RedirectResponse
    {
        Gate::authorize('integrations.edit');

        $webhook->update($this->validateEndpoint($request));

        if ($webhook->is_active) {
            $webhook->forceFill(['consecutive_failures' => 0])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook updated.')]);

        return back();
    }

    public function rotate(WebhookEndpoint $webhook): RedirectResponse
    {
        Gate::authorize('integrations.edit');

        $secret = 'whsec_'.Str::random(40);
        $webhook->forceFill(['secret' => $secret])->save();

        $this->audit->log('webhook.secret_rotated', $webhook);
        Inertia::flash('secret', ['endpoint_id' => $webhook->id, 'value' => $secret]);

        return back();
    }

    public function test(WebhookEndpoint $webhook, WebhookDispatcher $dispatcher): RedirectResponse
    {
        Gate::authorize('integrations.edit');

        $dispatcher->queue($webhook, WebhookEvent::Ping, ['message' => 'Webhook test from the school ERP.']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test event queued. Check the delivery log.')]);

        return back();
    }

    public function destroy(WebhookEndpoint $webhook): RedirectResponse
    {
        Gate::authorize('integrations.delete');

        $this->audit->log('webhook.deleted', $webhook, ['url' => $webhook->url]);
        $webhook->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook deleted.')]);

        return back();
    }

    /**
     * @return array{url: string, description: string|null, events: list<string>, is_active: bool}
     */
    private function validateEndpoint(Request $request): array
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:500', 'url:https'],
            'description' => ['nullable', 'string', 'max:150'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'distinct', Rule::in(array_column(WebhookEvent::subscribable(), 'value'))],
            'is_active' => ['boolean'],
        ]);

        if (($problem = $this->guard->problem($validated['url'])) !== null) {
            throw ValidationException::withMessages(['url' => $problem]);
        }

        return [
            'url' => $validated['url'],
            'description' => $validated['description'] ?? null,
            'events' => array_values($validated['events']),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
