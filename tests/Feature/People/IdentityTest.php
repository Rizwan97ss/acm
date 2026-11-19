<?php

use App\Enums\CertificateType;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\Records\CertificateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

test('uploaded photos are cropped to a square JPEG on the private disk and served only to permitted users', function () {
    $setup = communitySchool();
    $student = $setup['student'];
    $manager = userWithPermissions(['students.view', 'students.edit'], $setup['school']);

    $this->actingAs($manager)->post(route('people.photos.update', ['kind' => 'students', 'id' => $student->id]), [
        'photo' => UploadedFile::fake()->image('me.png', 800, 500),
    ])->assertSessionHasNoErrors();

    $path = $student->refresh()->photo_path;
    expect($path)->toEndWith('.jpg')->toStartWith("photos/{$setup['school']->id}/students/");
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));
    expect([$width, $height])->toBe([600, 600]);

    $url = route('people.photos.show', ['kind' => 'students', 'id' => $student->id]);
    $this->actingAs($manager)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($setup['studentUser'])->get($url)->assertOk();
    $this->actingAs($setup['otherGuardianUser'])->get($url)->assertNotFound();
});

test('photo uploads must be real images of a reasonable size', function () {
    $setup = communitySchool();
    $manager = userWithPermissions(['students.view', 'students.edit'], $setup['school']);
    $target = route('people.photos.update', ['kind' => 'students', 'id' => $setup['student']->id]);

    $this->actingAs($manager)->post($target, ['photo' => UploadedFile::fake()->create('evil.php', 10, 'image/jpeg')])->assertSessionHasErrors('photo');
    $this->actingAs($manager)->post($target, ['photo' => UploadedFile::fake()->image('tiny.png', 40, 40)])->assertSessionHasErrors('photo');
    $this->actingAs(userWithPermissions(['students.view'], $setup['school']))->post($target, ['photo' => UploadedFile::fake()->image('ok.png', 300, 300)])->assertForbidden();
});

test('student ID cards print for a section with a QR code that verifies the card', function () {
    $setup = communitySchool();
    $manager = userWithPermissions(['students.view'], $setup['school']);

    $this->actingAs($manager)->get(route('people.id-cards.print', ['type' => 'students', 'section_id' => $setup['section']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('standalone/id-cards')
            ->has('cards', 1)
            ->where('cards.0.number', $setup['student']->admission_no)
            ->where('cards.0.kind', 'student')
            ->where('cards.0.qr_svg', fn (string $svg) => str_contains($svg, '<svg'))
            ->where('cards.0.emergency.name', $setup['guardian']->full_name));

    $token = $setup['student']->refresh()->card_token;
    expect($token)->toHaveLength(40);

    $this->get(route('id-cards.verify', $token))
        ->assertInertia(fn (Assert $page) => $page->component('standalone/verify-id')->where('result.valid', true)->where('result.number', $setup['student']->admission_no));
});

test('reissuing a card makes the old QR code stop verifying', function () {
    $setup = communitySchool();
    $editor = userWithPermissions(['students.view', 'students.edit'], $setup['school']);
    $this->actingAs($editor)->get(route('people.id-cards.print', ['type' => 'students', 'ids' => [$setup['student']->id]]));
    $old = $setup['student']->refresh()->card_token;

    $this->actingAs($editor)->post(route('people.id-cards.reissue', ['type' => 'students', 'id' => $setup['student']->id]))->assertRedirect();

    expect($setup['student']->refresh()->card_token)->not->toBe($old);
    $this->get(route('id-cards.verify', $old))->assertInertia(fn (Assert $page) => $page->where('result', null));
});

test('a parent card lists every child at the school', function () {
    $setup = communitySchool();
    $sibling = Student::factory()->create(['school_id' => $setup['school']->id, 'first_name' => 'Sibling']);
    $sibling->guardians()->attach($setup['guardian']->id, ['relationship' => 'mother', 'is_primary' => true, 'is_emergency_contact' => true]);
    $viewer = userWithPermissions(['guardians.view'], $setup['school']);

    $this->actingAs($viewer)->get(route('people.id-cards.print', ['type' => 'parents', 'ids' => [$setup['guardian']->id]]))
        ->assertInertia(fn (Assert $page) => $page->where('cards.0.kind', 'parent')->has('cards.0.children', 2));
});

test('staff cards respect the teaching-only permission and other people cannot print cards', function () {
    $setup = communitySchool();
    Employee::factory()->create(['school_id' => $setup['school']->id, 'is_teaching' => true, 'status' => 'active']);
    Employee::factory()->create(['school_id' => $setup['school']->id, 'is_teaching' => false, 'status' => 'active']);

    $this->actingAs(userWithPermissions(['teachers.view'], $setup['school']))->get(route('people.id-cards.print', ['type' => 'staff']))
        ->assertInertia(fn (Assert $page) => $page->has('cards', 1)->where('cards.0.role', 'Teaching staff'));

    $this->actingAs(userWithPermissions(['library.view'], $setup['school']))->get(route('people.id-cards.print', ['type' => 'students', 'section_id' => $setup['section']->id]))->assertForbidden();
    $this->actingAs($setup['studentUser'])->get(route('people.id-cards.index'))->assertForbidden();
    $this->get(route('id-cards.verify', str_repeat('x', 40)))->assertInertia(fn (Assert $page) => $page->where('result', null));
});

test('printed certificates carry the student name and a verification QR code', function () {
    $setup = communitySchool();
    $principal = userWithPermissions(['certificates.view', 'certificates.create', 'certificates.approve'], $setup['school']);
    $certificate = app(CertificateService::class)->draft($setup['student'], CertificateType::Achievement, $principal);

    $this->actingAs($principal)->get(route('records.certificates.print', $certificate->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('standalone/certificate')
            ->where('certificate.student', $setup['student']->full_name)
            ->where('certificate.type', 'achievement')
            ->where('certificate.qr_svg', fn (string $svg) => str_contains($svg, '<svg')));
});

test('guardian photos belong to the guardian and their own account', function () {
    $setup = communitySchool();
    $guardian = Guardian::query()->whereKey($setup['guardian']->id)->firstOrFail();
    $editor = userWithPermissions(['guardians.view', 'guardians.edit'], $setup['school']);

    $this->actingAs($editor)->post(route('people.photos.update', ['kind' => 'guardians', 'id' => $guardian->id]), ['photo' => UploadedFile::fake()->image('p.jpg', 400, 400)])->assertSessionHasNoErrors();

    $url = route('people.photos.show', ['kind' => 'guardians', 'id' => $guardian->id]);
    $this->actingAs($setup['guardianUser'])->get($url)->assertOk();
    $this->actingAs($setup['studentUser'])->get($url)->assertNotFound();
});

test('one person can be found by name or number to open just their card', function () {
    $setup = communitySchool();
    $student = $setup['student'];
    $viewer = userWithPermissions(['students.view'], $setup['school']);
    $find = fn (string $type, string $q) => $this->actingAs($viewer)->getJson(route('people.id-cards.search', ['type' => $type, 'q' => $q]));

    $find('students', "{$student->first_name} {$student->last_name}")->assertOk()->assertJsonPath('0.id', $student->id);
    $find('students', $student->admission_no)->assertOk()->assertJsonPath('0.number', $student->admission_no);
    $find('students', 'zzzz-nobody')->assertOk()->assertJsonCount(0);
    $find('parents', $setup['guardian']->first_name)->assertForbidden();
    $this->actingAs($setup['guardianUser'])->getJson(route('people.id-cards.search', ['type' => 'students', 'q' => 'ab']))->assertForbidden();
});
