<?php

use App\Models\ContactInquiry;
use App\Models\Role;
use App\Services\Import\ImportRegistry;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, string>
 */
function validInquiry(array $overrides = []): array
{
    return [
        'name' => 'Amina Yusuf',
        'email' => 'Amina@Example.com',
        'phone' => '+92 300 1234567',
        'organization' => 'Crescent Grammar School',
        'role' => 'Principal',
        'school_size' => '300_1000',
        'interest' => 'demo',
        'message' => 'We would like to see admissions and fees.',
        ...$overrides,
    ];
}

test('the landing page shows figures counted from the application itself', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('facts.modules', count(config('rbac.modules')))
            ->where('facts.roles', count(config('rbac.roles')))
            ->where('facts.importers', count(ImportRegistry::IMPORTERS))
            ->where('facts.apiEndpoints', fn (int $count) => $count > 0)
            ->has('inquiryOptions.interests', count(ContactInquiry::INTERESTS)));
});

test('an enquiry from the landing page is saved with an encrypted phone number', function () {
    $this->from(route('home'))
        ->post(route('contact.store'), validInquiry())
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors();

    $inquiry = ContactInquiry::query()->sole();

    expect($inquiry->email)->toBe('amina@example.com')
        ->and($inquiry->phone)->toBe('+92 300 1234567')
        ->and(DB::table('contact_inquiries')->value('phone'))->not->toContain('1234567')
        ->and($inquiry->ip_address)->not->toBeNull()
        ->and($inquiry->read_at)->toBeNull();
});

test('enquiries are validated', function () {
    $this->post(route('contact.store'), validInquiry([
        'name' => '',
        'email' => 'not-an-email',
        'interest' => 'free-money',
        'message' => 'Hi',
    ]))->assertSessionHasErrors(['name', 'email', 'interest', 'message']);

    expect(ContactInquiry::query()->count())->toBe(0);
});

test('bots filling the hidden field are not stored but get the same response', function () {
    $this->from(route('home'))
        ->post(route('contact.store'), validInquiry(['website' => 'https://spam.example']))
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors();

    expect(ContactInquiry::query()->count())->toBe(0);
});

test('enquiries are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('contact.store'), validInquiry(['email' => "person{$attempt}@example.com"]));
    }

    $this->post(route('contact.store'), validInquiry())->assertTooManyRequests();

    expect(ContactInquiry::query()->count())->toBe(5);
});

test('administrators read, mark and delete enquiries', function () {
    $admin = userWithPermissions(['inquiries.view', 'inquiries.delete']);
    $unread = ContactInquiry::factory()->create(['name' => 'Unread Person']);
    ContactInquiry::factory()->read()->create(['name' => 'Read Person']);

    $this->actingAs($admin)->get(route('admin.inquiries.index', ['status' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inquiries')
            ->where('unread', 1)
            ->has('inquiries.data', 1)
            ->where('inquiries.data.0.name', 'Unread Person'));

    $this->actingAs($admin)->patch(route('admin.inquiries.update', $unread), ['read' => true])->assertRedirect();
    expect($unread->refresh()->read_at)->not->toBeNull();

    $this->actingAs($admin)->delete(route('admin.inquiries.destroy', $unread))->assertRedirect();
    expect(ContactInquiry::query()->whereKey($unread->id)->exists())->toBeFalse();
});

test('enquiries are hidden from people without the permission', function () {
    $inquiry = ContactInquiry::factory()->create();
    $viewer = userWithPermissions(['inquiries.view']);
    $teacher = userWithPermissions(['students.view']);

    $this->actingAs($teacher)->get(route('admin.inquiries.index'))->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.inquiries.destroy', $inquiry))->assertForbidden();

    expect(ContactInquiry::query()->count())->toBe(1);
});

test('principals do not receive the enquiries permission by default', function () {
    $this->artisan('rbac:sync')->assertSuccessful();

    $principal = Role::query()->where('slug', 'principal')->firstOrFail();

    expect(app(PermissionResolver::class)->permissionsForRole($principal))->not->toContain('inquiries.view');
});
