<?php

use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Announcement;
use App\Models\School;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Communication\AnnouncementService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const PUBLIC_HOOK = 'https://93.184.216.34/hooks/school';

function integrator(School $school): User
{
    return userWithPermissions(['integrations.view', 'integrations.create', 'integrations.edit', 'integrations.delete'], $school);
}

function endpointFor(School $school, array $events = ['announcement.published']): WebhookEndpoint
{
    return WebhookEndpoint::query()->create(['school_id' => $school->id, 'url' => PUBLIC_HOOK, 'secret' => 'whsec_test', 'events' => $events]);
}

test('creating an endpoint stores an encrypted secret shown once', function () {
    $school = School::factory()->create();

    $this->actingAs(integrator($school))
        ->post(route('admin.webhooks.store'), ['url' => PUBLIC_HOOK, 'events' => ['payment.recorded']])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('secret.endpoint_id', WebhookEndpoint::query()->value('id'));

    $raw = DB::table('webhook_endpoints')->value('secret');
    expect($raw)->not->toStartWith('whsec_')
        ->and(WebhookEndpoint::query()->sole()->secret)->toStartWith('whsec_');
});

test('unsafe target URLs are rejected', function (string $url) {
    $school = School::factory()->create();

    $this->actingAs(integrator($school))
        ->post(route('admin.webhooks.store'), ['url' => $url, 'events' => ['payment.recorded']])
        ->assertSessionHasErrors('url');
})->with([
    'plain http' => ['http://93.184.216.34/hook'],
    'loopback' => ['https://127.0.0.1/hook'],
    'private network' => ['https://10.0.0.5/hook'],
    'cloud metadata' => ['https://169.254.169.254/latest'],
    'credentials' => ['https://user:pass@93.184.216.34/hook'],
]);

test('host names that resolve to private addresses are rejected', function () {
    $guard = new class extends WebhookUrlGuard
    {
        protected function resolve(string $host): array
        {
            return ['192.168.1.20'];
        }
    };

    expect($guard->problem('https://intranet.example.com/hook'))->not->toBeNull();
});

test('deliveries are signed so the receiver can verify them', function () {
    $school = School::factory()->create();
    $endpoint = endpointFor($school);
    Http::fake(['93.184.216.34/*' => Http::response('ok')]);

    app(WebhookDispatcher::class)->dispatch($school->id, WebhookEvent::AnnouncementPublished, ['announcement_id' => 7]);

    Http::assertSent(function (HttpRequest $request): bool {
        $expected = 'sha256='.hash_hmac('sha256', $request->header('X-Webhook-Timestamp')[0].'.'.$request->body(), 'whsec_test');

        return $request->url() === PUBLIC_HOOK
            && hash_equals($expected, $request->header('X-Webhook-Signature')[0])
            && $request['event'] === 'announcement.published'
            && $request['data']['announcement_id'] === 7;
    });

    expect(WebhookDelivery::query()->sole())->status->toBe('succeeded')->attempts->toBe(1)
        ->and($endpoint->fresh()->consecutive_failures)->toBe(0);
});

test('endpoints only receive subscribed events from their own school', function () {
    $school = School::factory()->create();
    endpointFor($school, ['payment.recorded']);
    endpointFor(School::factory()->create(), ['announcement.published']);
    Queue::fake();

    app(WebhookDispatcher::class)->dispatch($school->id, WebhookEvent::AnnouncementPublished, []);

    Queue::assertNothingPushed();
    expect(WebhookDelivery::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('failed deliveries are recorded and endpoints are disabled after repeated failures', function () {
    config(['webhooks.disable_after_failures' => 2, 'webhooks.backoff' => []]);
    $school = School::factory()->create();
    $endpoint = endpointFor($school);
    Http::fake(['93.184.216.34/*' => Http::response('nope', 500)]);

    app(WebhookDispatcher::class)->dispatch($school->id, WebhookEvent::AnnouncementPublished, []);
    expect(WebhookDelivery::query()->sole())->status->toBe('failed')->response_status->toBe(500)
        ->and($endpoint->fresh())->is_active->toBeTrue()->consecutive_failures->toBe(1);

    app(WebhookDispatcher::class)->dispatch($school->id, WebhookEvent::AnnouncementPublished, []);
    expect($endpoint->fresh())->is_active->toBeFalse();
});

test('publishing an announcement queues a webhook after the transaction commits', function () {
    $school = School::factory()->create();
    endpointFor($school);
    Queue::fake();
    $announcement = Announcement::factory()->create(['school_id' => $school->id]);
    $this->actingAs($publisher = integrator($school));

    app(AnnouncementService::class)->publish($announcement, $publisher);

    Queue::assertPushed(DeliverWebhook::class, 1);
    expect(WebhookDelivery::query()->sole()->payload['data']['announcement_id'])->toBe($announcement->id);
});

test('managing webhooks requires the integrations permission', function () {
    $school = School::factory()->create();
    $endpoint = endpointFor($school);
    $viewer = userWithPermissions(['integrations.view'], $school);

    $this->actingAs($viewer)->get(route('admin.webhooks.index'))->assertOk();
    $this->actingAs($viewer)->post(route('admin.webhooks.test', $endpoint))->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.webhooks.destroy', $endpoint))->assertForbidden();
    $this->actingAs(userWithPermissions(['users.view'], $school))->get(route('admin.webhooks.index'))->assertForbidden();
});
