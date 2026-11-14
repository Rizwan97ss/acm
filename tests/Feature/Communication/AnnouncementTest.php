<?php

use App\Enums\Audience;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * teachingSchool() plus portal accounts: the enrolled student and their guardian (class 1),
 * and a guardian whose child is in a second class.
 *
 * @return array<string, mixed>
 */
function communitySchool(): array
{
    $setup = teachingSchool();
    $school = $setup['school'];

    $studentUser = userWithPermissions(['announcements.view', 'events.view', 'messages.view'], $school);
    $setup['student']->update(['user_id' => $studentUser->id]);

    $guardianUser = userWithPermissions(['announcements.view', 'events.view', 'messages.view', 'messages.create'], $school);
    $setup['guardian']->update(['user_id' => $guardianUser->id]);

    $otherClass = SchoolClass::factory()->create(['school_id' => $school->id]);
    $otherSection = Section::factory()->create(['school_class_id' => $otherClass->id, 'name' => 'B']);
    $otherStudent = Student::factory()->create(['school_id' => $school->id]);
    Enrollment::factory()->create(['student_id' => $otherStudent->id, 'section_id' => $otherSection->id, 'academic_year_id' => $setup['year']->id]);
    $otherGuardianUser = userWithPermissions(['announcements.view', 'events.view', 'messages.view', 'messages.create'], $school);
    $otherGuardian = Guardian::factory()->create(['school_id' => $school->id, 'user_id' => $otherGuardianUser->id]);
    $otherStudent->guardians()->attach($otherGuardian->id, ['relationship' => 'father', 'is_primary' => true, 'is_emergency_contact' => true]);

    $manager = userWithPermissions(['announcements.view', 'announcements.create', 'announcements.edit', 'announcements.delete', 'announcements.approve', 'events.view', 'events.create', 'events.edit', 'events.delete', 'messages.view', 'messages.create'], $school);

    return [...$setup, 'studentUser' => $studentUser, 'guardianUser' => $guardianUser, 'otherClass' => $otherClass, 'otherGuardianUser' => $otherGuardianUser, 'manager' => $manager];
}

/**
 * @return list<string>
 */
function visibleAnnouncementTitles(User $user): array
{
    $titles = [];

    test()->actingAs($user)->get(route('communication.announcements.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$titles) {
            $titles = collect($page->toArray()['props']['announcements']['data'])->pluck('title')->all();
        });

    return $titles;
}

test('publishing notifies only the targeted audience', function () {
    Notification::fake();
    $setup = communitySchool();
    $classId = $setup['section']->school_class_id;

    $this->actingAs($setup['manager'])->post(route('communication.announcements.store'), [
        'title' => 'Class 1 trip', 'body' => 'Bring a packed lunch.', 'audience' => 'guardians', 'school_class_id' => $classId, 'publish' => true,
    ])->assertRedirect(route('communication.announcements.index'));

    $announcement = Announcement::query()->sole();
    expect($announcement->published_at)->not->toBeNull()->and($announcement->published_by)->toBe($setup['manager']->id);

    Notification::assertSentTo($setup['guardianUser'], AnnouncementPublished::class);
    Notification::assertNotSentTo([$setup['otherGuardianUser'], $setup['studentUser'], $setup['classTeacher'], $setup['manager']], AnnouncementPublished::class);
});

test('drafts are saved without notifying anyone when the author cannot approve', function () {
    Notification::fake();
    $setup = communitySchool();
    $author = userWithPermissions(['announcements.view', 'announcements.create'], $setup['school']);

    $this->actingAs($author)->post(route('communication.announcements.store'), [
        'title' => 'Draft', 'body' => 'Text', 'audience' => 'everyone', 'publish' => true,
    ])->assertRedirect();

    expect(Announcement::query()->sole()->published_at)->toBeNull();
    Notification::assertNothingSent();

    $this->actingAs($author)->post(route('communication.announcements.publish', Announcement::query()->sole()))->assertForbidden();
});

test('readers see only live announcements addressed to them', function () {
    $setup = communitySchool();
    $school = $setup['school'];
    $classId = $setup['section']->school_class_id;

    Announcement::factory()->published()->create(['school_id' => $school->id, 'title' => 'For everyone']);
    Announcement::factory()->published()->create(['school_id' => $school->id, 'title' => 'Staff only', 'audience' => Audience::Staff]);
    Announcement::factory()->published()->create(['school_id' => $school->id, 'title' => 'Class 1 parents', 'audience' => Audience::Guardians, 'school_class_id' => $classId]);
    Announcement::factory()->published()->create(['school_id' => $school->id, 'title' => 'Other class parents', 'audience' => Audience::Guardians, 'school_class_id' => $setup['otherClass']->id]);
    Announcement::factory()->published()->create(['school_id' => $school->id, 'title' => 'All students', 'audience' => Audience::Students]);
    Announcement::factory()->create(['school_id' => $school->id, 'title' => 'Unpublished draft']);
    Announcement::factory()->published()->create(['school_id' => $school->id, 'title' => 'Expired', 'expires_on' => now()->subDay()->toDateString()]);
    Announcement::factory()->published()->create(['title' => 'Another school']);

    expect(visibleAnnouncementTitles($setup['guardianUser']))->toEqualCanonicalizing(['For everyone', 'Class 1 parents'])
        ->and(visibleAnnouncementTitles($setup['studentUser']))->toEqualCanonicalizing(['For everyone', 'All students'])
        ->and(visibleAnnouncementTitles($setup['otherGuardianUser']))->toEqualCanonicalizing(['For everyone', 'Other class parents'])
        ->and(visibleAnnouncementTitles(userWithPermissions(['announcements.view'], $school)))->toEqualCanonicalizing(['For everyone', 'Staff only']);

    expect(visibleAnnouncementTitles($setup['manager']))->toHaveCount(7)->not->toContain('Another school');
});

test('a class can only be targeted for students or guardians', function () {
    $setup = communitySchool();

    $this->actingAs($setup['manager'])->post(route('communication.announcements.store'), [
        'title' => 'Bad', 'body' => 'Text', 'audience' => 'staff', 'school_class_id' => $setup['section']->school_class_id,
    ])->assertSessionHasErrors('school_class_id');

    $this->actingAs($setup['manager'])->post(route('communication.announcements.store'), [
        'title' => 'Bad', 'body' => 'Text', 'audience' => 'students', 'school_class_id' => SchoolClass::factory()->create()->id,
    ])->assertSessionHasErrors('school_class_id');
});

test('readers cannot create, publish or delete announcements', function () {
    $setup = communitySchool();
    $announcement = Announcement::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs($setup['guardianUser'])->post(route('communication.announcements.store'), ['title' => 'x', 'body' => 'y', 'audience' => 'everyone'])->assertForbidden();
    $this->actingAs($setup['guardianUser'])->post(route('communication.announcements.publish', $announcement))->assertForbidden();
    $this->actingAs($setup['guardianUser'])->delete(route('communication.announcements.destroy', $announcement))->assertForbidden();
});

test('opening a notification marks it read and follows its link', function () {
    $setup = communitySchool();

    $this->actingAs($setup['manager'])->post(route('communication.announcements.store'), [
        'title' => 'Sports day', 'body' => 'Friday', 'audience' => 'everyone', 'publish' => true,
    ]);

    $notification = $setup['guardianUser']->unreadNotifications()->sole();

    $this->actingAs($setup['guardianUser'])->get(route('communication.announcements.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.unreadNotifications', 1));

    $this->actingAs($setup['guardianUser'])
        ->get(route('notifications.open', $notification->id))
        ->assertRedirect($notification->data['url']);

    expect($setup['guardianUser']->unreadNotifications()->count())->toBe(0);

    $this->actingAs($setup['studentUser'])->get(route('notifications.open', $notification->id))->assertNotFound();
});
