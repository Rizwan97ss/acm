<?php

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\Guardian;
use App\Models\Mark;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\GradeScaleService;
use App\Services\Academics\ResultService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Exam, 1: ExamPaper}
 */
function examWithPaper(array $setup): array
{
    $scale = app(GradeScaleService::class)->defaultFor($setup['school']->id);
    $exam = Exam::factory()->create(['school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'grade_scale_id' => $scale->id]);
    $paper = ExamPaper::factory()->create([
        'school_id' => $setup['school']->id, 'exam_id' => $exam->id, 'school_class_id' => $setup['section']->school_class_id,
        'subject_id' => $setup['subject']->id, 'max_marks' => 50, 'pass_marks' => 20,
    ]);

    return [$exam, $paper];
}

function marksRoute(Exam $exam, ExamPaper $paper, array $setup): string
{
    return route('academics.exams.marks.update', [$exam, $paper, $setup['section']]);
}

test('exam papers are generated for every subject of every class', function () {
    $setup = teachingSchool();
    $admin = userWithPermissions(['exams.view', 'exams.create', 'exams.edit'], $setup['school']);
    $scale = app(GradeScaleService::class)->defaultFor($setup['school']->id);
    $exam = Exam::factory()->create(['school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'grade_scale_id' => $scale->id]);

    $this->actingAs($admin)->post(route('academics.exams.papers.generate', $exam))->assertRedirect(route('academics.exams.show', $exam));

    $paper = ExamPaper::query()->withoutGlobalScopes()->sole();
    expect($paper->subject_id)->toBe($setup['subject']->id)
        ->and((float) $paper->max_marks)->toBe(100.0)
        ->and((float) $paper->pass_marks)->toBe(40.0);
});

test('the class teacher enters marks within the maximum', function () {
    $setup = teachingSchool();
    [$exam, $paper] = examWithPaper($setup);
    $row = fn (mixed $marks) => ['marks' => [['student_id' => $setup['student']->id, 'marks_obtained' => $marks, 'is_absent' => false]]];

    $this->actingAs($setup['classTeacher'])->put(marksRoute($exam, $paper, $setup), $row(60))->assertSessionHasErrors();
    $this->actingAs($setup['classTeacher'])->put(marksRoute($exam, $paper, $setup), $row(42.5))->assertSessionHasNoErrors();

    expect((float) Mark::query()->withoutGlobalScopes()->sole()->marks_obtained)->toBe(42.5);
});

test('teachers cannot enter marks for subjects and sections they do not teach', function () {
    $setup = teachingSchool();
    [$exam, $paper] = examWithPaper($setup);
    $otherTeacher = userWithPermissions(['grades.view', 'grades.create', 'grades.edit'], $setup['school']);

    $this->actingAs($otherTeacher)->put(marksRoute($exam, $paper, $setup), [
        'marks' => [['student_id' => $setup['student']->id, 'marks_obtained' => 30, 'is_absent' => false]],
    ])->assertForbidden();

    expect(Mark::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('results compute totals, grades, pass or fail and competition ranks', function () {
    $setup = teachingSchool();
    [$exam, $paper] = examWithPaper($setup);
    $students = collect([46, 46, 10, null])->map(function (?int $score) use ($setup, $paper) {
        $student = Student::factory()->create(['school_id' => $setup['school']->id]);
        Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);

        if ($score !== null) {
            Mark::query()->create(['school_id' => $setup['school']->id, 'exam_paper_id' => $paper->id, 'student_id' => $student->id, 'marks_obtained' => $score]);
        }

        return $student;
    });
    Mark::query()->create(['school_id' => $setup['school']->id, 'exam_paper_id' => $paper->id, 'student_id' => $setup['student']->id, 'marks_obtained' => 30]);

    $rows = collect(app(ResultService::class)->forSection($exam, $setup['section'])['rows'])->keyBy('student.id');

    expect($rows[$students[0]->id])->toMatchArray(['percentage' => 92.0, 'grade' => 'A+', 'rank' => 1, 'passed' => true])
        ->and($rows[$students[1]->id]['rank'])->toBe(1)
        ->and($rows[$setup['student']->id])->toMatchArray(['percentage' => 60.0, 'grade' => 'C', 'rank' => 3, 'passed' => true])
        ->and($rows[$students[2]->id])->toMatchArray(['grade' => 'F', 'rank' => 4, 'passed' => false])
        ->and($rows[$students[3]->id])->toMatchArray(['complete' => false, 'rank' => null, 'passed' => null]);
});

test('publishing needs marks, locks mark entry, and unpublishing unlocks it', function () {
    $setup = teachingSchool();
    [$exam, $paper] = examWithPaper($setup);
    $principal = userWithPermissions(['exams.view', 'exams.approve'], $setup['school']);
    $marks = ['marks' => [['student_id' => $setup['student']->id, 'marks_obtained' => 30, 'is_absent' => false]]];

    $this->actingAs($principal)->post(route('academics.exams.publish', $exam))->assertSessionHasErrors('exam');
    $this->actingAs($setup['classTeacher'])->put(marksRoute($exam, $paper, $setup), $marks)->assertSessionHasNoErrors();
    $this->actingAs($principal)->post(route('academics.exams.publish', $exam))->assertSessionHasNoErrors();

    $this->actingAs($setup['classTeacher'])->put(marksRoute($exam, $paper, $setup), $marks)->assertSessionHasErrors('exam');

    $this->actingAs($principal)->delete(route('academics.exams.unpublish', $exam));
    $this->actingAs($setup['classTeacher'])->put(marksRoute($exam, $paper, $setup), $marks)->assertSessionHasNoErrors();
    expect($exam->fresh()->published_at)->toBeNull();
});

test('guardians see their child\'s report card only once results are published', function () {
    $setup = teachingSchool();
    [$exam, $paper] = examWithPaper($setup);
    Mark::query()->create(['school_id' => $setup['school']->id, 'exam_paper_id' => $paper->id, 'student_id' => $setup['student']->id, 'marks_obtained' => 45]);
    $parent = User::factory()->forSchool($setup['school'])->create();
    $setup['guardian']->update(['user_id' => $parent->id]);
    $otherChild = Student::factory()->create(['school_id' => $setup['school']->id]);
    $route = route('academics.exams.report-card', [$exam, $setup['student']]);

    $this->actingAs($parent)->get($route)->assertNotFound();

    $exam->forceFill(['published_at' => now()])->save();

    $this->actingAs($parent)->get($route)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('academics/exams/report-card')
        ->where('result.percentage', 90)
        ->where('result.grade', 'A+')
    );
    $this->actingAs($parent)->get(route('academics.exams.report-card', [$exam, $otherChild]))->assertNotFound();
});

test('the guardian progress page lists published results and attendance', function () {
    $setup = teachingSchool();
    [$exam, $paper] = examWithPaper($setup);
    Mark::query()->create(['school_id' => $setup['school']->id, 'exam_paper_id' => $paper->id, 'student_id' => $setup['student']->id, 'marks_obtained' => 25]);
    $parent = User::factory()->forSchool($setup['school'])->create();
    Guardian::query()->withoutGlobalScopes()->whereKey($setup['guardian']->id)->update(['user_id' => $parent->id]);

    $this->actingAs($parent)->get(route('academics.progress'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('children', 1)->has('children.0.results', 0));

    $exam->forceFill(['published_at' => now()])->save();

    $this->actingAs($parent)->get(route('academics.progress'))->assertInertia(fn (Assert $page) => $page->where('children.0.results.0.grade', 'D'));
});

test('grading scales must start at zero percent', function () {
    $setup = teachingSchool();
    $principal = userWithPermissions(['grades.view', 'grades.approve'], $setup['school']);

    $this->actingAs($principal)->post(route('academics.grade-scales.store'), [
        'name' => 'Gap scale',
        'is_default' => false,
        'bands' => [
            ['grade' => 'P', 'min_percentage' => 50, 'is_passing' => true],
            ['grade' => 'F', 'min_percentage' => 10, 'is_passing' => false],
        ],
    ])->assertSessionHasErrors('bands');

    $this->assertDatabaseMissing('grade_scales', ['name' => 'Gap scale']);
});
