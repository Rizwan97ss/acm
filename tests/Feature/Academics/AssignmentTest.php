<?php

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\Rbac\PermissionResolver;

/**
 * @return array<string, mixed>
 */
function assignmentPayload(array $setup, array $overrides = []): array
{
    return [
        'section_id' => $setup['section']->id,
        'subject_id' => $setup['subject']->id,
        'type' => 'homework',
        'title' => 'Fractions worksheet',
        'description' => 'Complete questions 1-10.',
        'due_on' => now()->addDays(3)->toDateString(),
        'max_marks' => 10,
        ...$overrides,
    ];
}

function studentAccount(array $setup): User
{
    $account = User::factory()->forSchool($setup['school'])->create();
    $setup['student']->update(['user_id' => $account->id]);

    return $account;
}

test('teachers set work for sections and subjects they teach', function () {
    $setup = teachingSchool();
    $otherSection = Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
    $foreignSubject = Subject::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs($setup['classTeacher'])->post(route('academics.assignments.store'), assignmentPayload($setup))->assertSessionHasNoErrors();
    $this->actingAs($setup['classTeacher'])->post(route('academics.assignments.store'), assignmentPayload($setup, ['section_id' => $otherSection->id]))->assertForbidden();
    $this->actingAs($setup['classTeacher'])->post(route('academics.assignments.store'), assignmentPayload($setup, ['subject_id' => $foreignSubject->id]))->assertSessionHasErrors('subject_id');

    $assignment = Assignment::query()->withoutGlobalScopes()->sole();
    expect($assignment->created_by)->toBe($setup['classTeacher']->id)
        ->and($assignment->academic_year_id)->toBe($setup['year']->id);
});

test('students submit work and may replace it until it is graded', function () {
    $setup = teachingSchool();
    $account = studentAccount($setup);
    $this->actingAs($setup['classTeacher'])->post(route('academics.assignments.store'), assignmentPayload($setup));
    $assignment = Assignment::query()->withoutGlobalScopes()->sole();

    $this->actingAs($account)->post(route('academics.assignments.submit', $assignment), ['content' => 'First try'])->assertSessionHasNoErrors();
    $this->actingAs($account)->post(route('academics.assignments.submit', $assignment), ['content' => 'Second try'])->assertSessionHasNoErrors();

    $submission = AssignmentSubmission::query()->withoutGlobalScopes()->sole();
    expect($submission->content)->toBe('Second try');

    $this->actingAs($setup['classTeacher'])->put(route('academics.assignments.grade', [$assignment, $submission]), ['marks' => 12])->assertSessionHasErrors('marks');
    $this->actingAs($setup['classTeacher'])->put(route('academics.assignments.grade', [$assignment, $submission]), ['marks' => 8, 'feedback' => 'Good work'])->assertSessionHasNoErrors();
    $this->actingAs($account)->post(route('academics.assignments.submit', $assignment), ['content' => 'Too late'])->assertSessionHasErrors('content');

    expect($submission->fresh())->content->toBe('Second try')->feedback->toBe('Good work');
});

test('students cannot see or submit work set for another section', function () {
    $setup = teachingSchool();
    $account = studentAccount($setup);
    $otherSection = Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
    $assignment = Assignment::factory()->create([
        'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id,
        'section_id' => $otherSection->id, 'subject_id' => $setup['subject']->id,
    ]);
    $account->roles()->attach(Role::factory()->withPermissions(['assignments.view'])->create()->id);
    app(PermissionResolver::class)->flush();

    $this->actingAs($account)->get(route('academics.assignments.show', $assignment))->assertForbidden();
    $this->actingAs($account)->post(route('academics.assignments.submit', $assignment), ['content' => 'Hi'])->assertSessionHasErrors('content');

    expect(AssignmentSubmission::query()->withoutGlobalScopes()->count())->toBe(0);
});
