<?php

use App\Enums\ApiAbility;
use App\Enums\Audience;
use App\Enums\SettingScope;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

/**
 * A staff account with students and invoices access in the school of the given setup.
 */
function apiStaff(array $setup): User
{
    return userWithPermissions(['students.view', 'invoices.view', 'attendance.view', 'timetables.view'], $setup['school']);
}

/**
 * communitySchool() where the guardian portal account also holds the parent's read permissions.
 *
 * @return array<string, mixed>
 */
function apiCommunity(): array
{
    $setup = communitySchool();
    $parent = userWithPermissions(['timetables.view', 'attendance.view', 'invoices.view', 'announcements.view', 'events.view'], $setup['school']);
    $setup['guardian']->update(['user_id' => $parent->id]);
    $setup['otherStudent'] = Student::query()->withoutGlobalScopes()->whereKeyNot($setup['student']->id)->sole();

    return [...$setup, 'parent' => $parent];
}

describe('mobile sign-in', function () {
    test('valid credentials return a bearer token that works on the API', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();

        $token = $this->postJson(route('api.v1.auth.token.store'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->json('token');

        $this->withToken($token)->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.id', $user->id);
        expect(PersonalAccessToken::query()->sole()->expires_at)->not->toBeNull();
    });

    test('wrong passwords, deactivated and unverified accounts get no token', function (string $state, string $password) {
        $factory = User::factory()->forSchool(School::factory()->create());
        $user = ($state === 'active' ? $factory : $factory->{$state}())->create();

        $this->postJson(route('api.v1.auth.token.store'), ['email' => $user->email, 'password' => $password, 'device_name' => 'Phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        expect(PersonalAccessToken::query()->count())->toBe(0);
    })->with([
        'wrong password' => ['active', 'wrong-password'],
        'deactivated' => ['inactive', 'password'],
        'unverified' => ['unverified', 'password'],
    ]);

    test('accounts with two-factor need a valid code, and recovery codes are single use', function () {
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $user = User::factory()->forSchool(School::factory()->create())->withTwoFactor()->create();
        $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();
        $credentials = ['email' => $user->email, 'password' => 'password', 'device_name' => 'Phone'];

        $this->postJson(route('api.v1.auth.token.store'), $credentials)->assertJsonValidationErrors('code');
        $this->postJson(route('api.v1.auth.token.store'), [...$credentials, 'code' => '000000'])->assertJsonValidationErrors('code');

        $this->postJson(route('api.v1.auth.token.store'), [...$credentials, 'code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertCreated();
        $this->postJson(route('api.v1.auth.token.store'), [...$credentials, 'code' => 'recovery-code-1'])->assertCreated();
        $this->postJson(route('api.v1.auth.token.store'), [...$credentials, 'code' => 'recovery-code-1'])->assertJsonValidationErrors('code');
    });

    test('when the school requires two-factor, accounts without it cannot sign in', function () {
        $school = School::factory()->create();
        $user = User::factory()->forSchool($school)->create();
        app(SettingsService::class)->put(SettingScope::Global, 0, ['security.require_two_factor' => true]);

        $this->postJson(route('api.v1.auth.token.store'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Phone'])
            ->assertJsonValidationErrors('email');
    });

    test('signing out revokes only the token in use', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();
        $current = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($current)->deleteJson(route('api.v1.auth.token.destroy'))->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);
    });
});

describe('access rules', function () {
    test('requests without a valid token are rejected', function () {
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->withToken('1|not-a-real-token')->getJson(route('api.v1.me'))->assertUnauthorized();
    });

    test('tokens of deactivated accounts stop working', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();
        $token = $user->createToken('phone')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson(route('api.v1.me'))->assertForbidden();
    });

    test('expired tokens are rejected', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();
        $token = $user->createToken('phone', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
    });

    test('a token only opens the endpoint groups in its scopes', function () {
        $setup = teachingSchool();
        Sanctum::actingAs(apiStaff($setup), [ApiAbility::Finance->value]);

        $this->getJson(route('api.v1.invoices.index'))->assertOk();
        $this->getJson(route('api.v1.students.index'))->assertForbidden();
    });

    test('scopes never widen the owner\'s permissions', function () {
        $setup = teachingSchool();
        Sanctum::actingAs(userWithPermissions(['announcements.view'], $setup['school']), ['*']);

        $this->getJson(route('api.v1.students.index'))->assertForbidden();
        $this->getJson(route('api.v1.invoices.index'))->assertForbidden();
    });

    test('staff cannot read students of another school', function () {
        $setup = teachingSchool();
        $elsewhere = Student::factory()->create(['school_id' => School::factory()->create()->id]);
        Sanctum::actingAs(apiStaff($setup), ['*']);

        $this->getJson(route('api.v1.students.index'))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(route('api.v1.students.show', $elsewhere))->assertNotFound();
    });

    test('encrypted personal fields are not exposed', function () {
        $setup = teachingSchool();
        $setup['student']->update(['national_id' => '35202-1234567-1', 'medical_notes' => 'Asthma', 'address' => '1 Mall Road']);
        Sanctum::actingAs(apiStaff($setup), ['*']);

        $this->getJson(route('api.v1.students.show', $setup['student']))
            ->assertOk()
            ->assertJsonMissingPath('data.national_id')
            ->assertJsonMissingPath('data.medical_notes')
            ->assertJsonMissingPath('data.address');
    });
});

describe('portal accounts', function () {
    test('guardians see only their own children', function () {
        $setup = apiCommunity();
        Sanctum::actingAs($setup['parent'], ['*']);

        $this->getJson(route('api.v1.me'))->assertJsonPath('data.students.0.id', $setup['student']->id)->assertJsonCount(1, 'data.students');
        $this->getJson(route('api.v1.students.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $setup['student']->id);
        $this->getJson(route('api.v1.students.show', $setup['otherStudent']))->assertNotFound();
        $this->getJson(route('api.v1.students.attendance', $setup['otherStudent']))->assertNotFound();
    });

    test('guardians get their child\'s attendance with a yearly summary', function () {
        $setup = apiCommunity();
        AttendanceRecord::factory()->create(['school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'section_id' => $setup['section']->id, 'student_id' => $setup['student']->id, 'date' => now()->toDateString(), 'status' => 'absent']);
        Sanctum::actingAs($setup['parent'], ['*']);

        $this->getJson(route('api.v1.students.attendance', $setup['student']))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'absent')
            ->assertJsonPath('summary.absent', 1);
    });

    test('guardians see only their children\'s invoices', function () {
        $setup = apiCommunity();
        $own = Invoice::factory()->create(['school_id' => $setup['school']->id, 'student_id' => $setup['student']->id, 'academic_year_id' => $setup['year']->id]);
        $theirs = Invoice::factory()->create(['school_id' => $setup['school']->id, 'student_id' => $setup['otherStudent']->id, 'academic_year_id' => $setup['year']->id]);
        Sanctum::actingAs($setup['parent'], ['*']);

        $this->getJson(route('api.v1.invoices.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson(route('api.v1.invoices.show', $theirs))->assertNotFound();
    });

    test('guardians get their children\'s timetable only', function () {
        $setup = apiCommunity();
        $otherSection = $setup['otherClass']->sections()->sole();
        TimetableEntry::factory()->create(['section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id, 'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id]);
        TimetableEntry::factory()->create(['section_id' => $otherSection->id, 'academic_year_id' => $setup['year']->id, 'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id]);
        Sanctum::actingAs($setup['parent'], ['*']);

        $this->getJson(route('api.v1.timetable'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.section.id', $setup['section']->id);
        $this->getJson(route('api.v1.timetable', ['section_id' => $otherSection->id]))->assertNotFound();
    });

    test('announcements are limited to live ones addressed to the viewer', function () {
        $setup = apiCommunity();
        $school = ['school_id' => $setup['school']->id];
        $everyone = Announcement::factory()->published()->create($school);
        Announcement::factory()->create($school);
        Announcement::factory()->published()->create([...$school, 'audience' => Audience::Staff]);
        Sanctum::actingAs($setup['parent'], ['*']);

        $this->getJson(route('api.v1.announcements.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $everyone->id);
    });
});

describe('notifications', function () {
    test('users can list and mark their own notifications read', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();
        $other = User::factory()->forSchool($user->school)->create();
        foreach ([$user, $other] as $owner) {
            $owner->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['title' => 'Hello']]);
        }
        Sanctum::actingAs($user, ['*']);

        $this->getJson(route('api.v1.notifications.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('unread_count', 1);
        $this->postJson(route('api.v1.notifications.read', $other->notifications()->sole()->id))->assertNotFound();
        $this->postJson(route('api.v1.notifications.read-all'))->assertNoContent();

        expect($user->unreadNotifications()->count())->toBe(0)
            ->and($other->unreadNotifications()->count())->toBe(1);
    });
});

describe('API tokens settings page', function () {
    test('creating a token flashes the plain value once and stores only a hash', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), ['name' => 'Reports', 'abilities' => [ApiAbility::Finance->value], 'expires_in_days' => 30])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('token.value');

        $token = PersonalAccessToken::query()->sole();
        expect($token->abilities)->toBe([ApiAbility::Finance->value])
            ->and($token->expires_at?->isFuture())->toBeTrue();
    });

    test('unknown scopes are rejected', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), ['name' => 'Admin', 'abilities' => ['*']])
            ->assertSessionHasErrors('abilities.0');
    });

    test('users can revoke their own tokens but not someone else\'s', function () {
        $user = User::factory()->forSchool(School::factory()->create())->create();
        $mine = $user->createToken('mine')->accessToken;
        $theirs = User::factory()->forSchool($user->school)->create()->createToken('theirs')->accessToken;

        $this->actingAs($user)->delete(route('api-tokens.destroy', $theirs->id))->assertNotFound();
        $this->actingAs($user)->delete(route('api-tokens.destroy', $mine->id))->assertRedirect(route('api-tokens.index'));

        expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['theirs']);
    });
});
