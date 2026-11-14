<?php

use App\Enums\WebhookEvent;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Audit\AuditLogger;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

test('every audit entry is also written to the audit log channel with masked values', function () {
    $path = captureLogChannel(AuditLogger::CHANNEL);
    $user = User::factory()->forSchool(School::factory()->create())->create();

    $user->update(['name' => 'Renamed Person', 'phone' => '+92 300 1234567']);

    $entry = AuditLog::query()->where('event', 'user.updated')->sole();
    $log = File::get($path);

    expect($log)->toContain('user.updated')
        ->toContain('"audit_id":'.$entry->id)
        ->toContain('Renamed Person')
        ->toContain('[encrypted]')
        ->not->toContain('1234567');
});

test('refused API sign-ins are audited and logged without the password', function () {
    $path = captureLogChannel(AuditLogger::CHANNEL);
    $user = User::factory()->forSchool(School::factory()->create())->create();

    $this->postJson(route('api.v1.auth.token.store'), ['email' => $user->email, 'password' => 'Wrong-Secret-99', 'device_name' => 'Phone'])
        ->assertUnprocessable();

    expect(AuditLog::query()->where('event', 'api.sign_in_failed')->sole()->new_values)->toBe(['email' => $user->email, 'reason' => 'invalid_credentials'])
        ->and(File::get($path))->toContain('api.sign_in_failed')->not->toContain('Wrong-Secret-99');
});

test('failed webhook deliveries are logged without the payload or secret', function () {
    config(['webhooks.backoff' => []]);
    $path = captureLogChannel('capture');
    config(['logging.default' => 'capture']);
    $school = School::factory()->create();
    WebhookEndpoint::query()->create(['school_id' => $school->id, 'url' => 'https://93.184.216.34/hook', 'secret' => 'whsec_do_not_log', 'events' => ['announcement.published']]);
    Http::fake(['93.184.216.34/*' => Http::response('nope', 500)]);

    app(WebhookDispatcher::class)->dispatch($school->id, WebhookEvent::AnnouncementPublished, ['title' => 'Private title']);

    expect(File::get($path))->toContain('Webhook delivery failed permanently.')
        ->toContain('"response_status":500')
        ->not->toContain('whsec_do_not_log')
        ->not->toContain('Private title');
});
