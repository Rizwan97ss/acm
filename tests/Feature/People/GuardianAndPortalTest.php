<?php

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

function guardianAdmin(School $school, array $extra = []): User
{
    return userWithPermissions([...app(PermissionRegistry::class)->expand(['students.*', 'guardians.*']), ...$extra], $school);
}

test('the first linked guardian becomes primary and a new primary demotes the old one', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);
    $admin = guardianAdmin($school);

    $this->actingAs($admin)->post(route('people.students.guardians.store', $student), [
        'relationship' => 'mother', 'is_primary' => false, 'is_emergency_contact' => true,
        'guardian' => ['first_name' => 'Sara', 'last_name' => 'Khan', 'phone' => '+1 555 123 4567', 'email' => 'Sara@Example.com'],
    ])->assertSessionHasNoErrors();
    $father = Guardian::factory()->create(['school_id' => $school->id]);
    $this->actingAs($admin)->post(route('people.students.guardians.store', $student), [
        'guardian_id' => $father->id, 'relationship' => 'father', 'is_primary' => true, 'is_emergency_contact' => true,
    ])->assertSessionHasNoErrors();

    $links = $student->guardians()->get()->mapWithKeys(fn (Guardian $guardian) => [$guardian->first_name => (bool) $guardian->pivot->is_primary]);
    expect($links->all())->toBe([$father->first_name => true, 'Sara' => false]);
    $this->assertDatabaseHas('guardians', ['first_name' => 'Sara', 'email' => 'sara@example.com']);
});

test('unlinking the primary guardian promotes the remaining guardian', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);
    [$first, $second] = Guardian::factory()->count(2)->create(['school_id' => $school->id]);
    $student->guardians()->attach($first->id, ['relationship' => 'mother', 'is_primary' => true]);
    $student->guardians()->attach($second->id, ['relationship' => 'father', 'is_primary' => false]);

    $this->actingAs(guardianAdmin($school))->delete(route('people.students.guardians.destroy', [$student, $first]));

    expect((bool) $student->guardians()->sole()->pivot->is_primary)->toBeTrue();
});

test('guardians of another school cannot be linked', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);
    $foreign = Guardian::factory()->create();

    $this->actingAs(guardianAdmin($school))->post(route('people.students.guardians.store', $student), [
        'guardian_id' => $foreign->id, 'relationship' => 'father', 'is_primary' => true, 'is_emergency_contact' => true,
    ])->assertSessionHasErrors('guardian_id');

    expect($student->guardians()->count())->toBe(0);
});

test('guardians still linked to students cannot be deleted', function () {
    $school = School::factory()->create();
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);
    Student::factory()->create(['school_id' => $school->id])->guardians()->attach($guardian->id, ['relationship' => 'mother']);

    $this->actingAs(guardianAdmin($school))->delete(route('people.guardians.destroy', $guardian))->assertSessionHasErrors('guardian');

    $this->assertNotSoftDeleted($guardian);
});

test('guardians can be found by exact phone number', function () {
    $school = School::factory()->create();
    Guardian::factory()->create(['school_id' => $school->id, 'phone' => '+1 (555) 222-3333', 'first_name' => 'Match']);
    Guardian::factory()->create(['school_id' => $school->id, 'phone' => '+1 555 999 0000']);

    $this->actingAs(guardianAdmin($school))
        ->getJson(route('people.guardians.search', ['q' => '15552223333']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.first_name', 'Match');
});

test('inviting a student creates a portal account and emails a password reset link', function () {
    Notification::fake();
    $this->artisan('rbac:sync');
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id, 'email' => 'pupil@example.com']);

    $this->actingAs(superAdmin())
        ->post(route('admin.schools.switch', $school));
    $this->post(route('people.students.portal-account.store', $student))->assertSessionHasNoErrors();

    $account = User::query()->where('email', 'pupil@example.com')->sole();
    expect($account->school_id)->toBe($school->id)
        ->and($account->roles->pluck('slug')->all())->toBe(['student'])
        ->and($student->fresh()->user_id)->toBe($account->id);
    Notification::assertSentTo($account, ResetPassword::class);
});

test('portal invitations require an email, a single account and the users.create permission', function () {
    $this->artisan('rbac:sync');
    $school = School::factory()->create();
    $withoutEmail = Student::factory()->create(['school_id' => $school->id, 'email' => null]);
    $student = Student::factory()->create(['school_id' => $school->id, 'email' => 'kid@example.com']);
    $admin = guardianAdmin($school, app(PermissionRegistry::class)->expand(['*.view', 'users.create', 'timetables.*', 'attendance.*', 'exams.*', 'grades.*', 'assignments.*', 'invoices.*', 'announcements.*', 'events.*', 'messages.*', 'library.*', 'documents.*']));

    $this->actingAs(guardianAdmin($school))->post(route('people.students.portal-account.store', $student))->assertForbidden();
    $this->actingAs($admin)->post(route('people.students.portal-account.store', $withoutEmail))->assertSessionHasErrors('account');
    $this->actingAs($admin)->post(route('people.students.portal-account.store', $student))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('people.students.portal-account.store', $student))->assertSessionHasErrors('account');

    expect(User::query()->where('email', 'kid@example.com')->count())->toBe(1);
});
