<?php

use App\Enums\PublishStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\Enrollment;
use App\Models\OnlineExam;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamQuestion;
use App\Models\Student;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A class teacher (section A), an outsider teacher, the enrolled student and guardian with
 * portal access, a student of another class, and a published exam on now:
 * Q1 single choice 2 marks (−0.5), Q2 multiple choice 3, Q3 true/false 1, Q4 essay 4.
 *
 * @return array<string, mixed>
 */
function examSchool(): array
{
    $setup = communitySchool();
    $school = $setup['school'];
    $examPermissions = ['online_exams.view', 'online_exams.create', 'online_exams.edit', 'online_exams.delete', 'online_exams.export', 'online_exams.publish', 'question_bank.view', 'question_bank.create', 'question_bank.edit', 'question_bank.delete'];

    $teacher = userWithPermissions($examPermissions, $school);
    $setup['section']->update(['class_teacher_id' => $teacher->id]);
    $outsider = userWithPermissions($examPermissions, $school);

    $studentUser = userWithPermissions(['online_exams.view'], $school);
    $setup['student']->update(['user_id' => $studentUser->id]);
    $parentUser = userWithPermissions(['online_exams.view'], $school);
    $setup['guardian']->update(['user_id' => $parentUser->id]);

    $otherStudent = Student::query()->whereKeyNot($setup['student']->id)->firstOrFail();
    $otherUser = userWithPermissions(['online_exams.view'], $school);
    $otherStudent->update(['user_id' => $otherUser->id]);

    $exam = OnlineExam::factory()->published()->create([
        'school_id' => $school->id,
        'academic_year_id' => $setup['year']->id,
        'school_class_id' => $setup['section']->school_class_id,
        'subject_id' => $setup['subject']->id,
        'total_marks' => 10,
        'passing_marks' => 5,
        'negative_marking' => true,
        'show_rank' => true,
        'created_by' => $teacher->id,
    ]);

    $question = fn (array $attributes) => OnlineExamQuestion::query()->create(['school_id' => $school->id, 'online_exam_id' => $exam->id, ...$attributes]);
    $questions = [
        'single' => $question(['type' => 'single_choice', 'prompt' => 'Capital of France?', 'options' => [['id' => 'a', 'text' => 'Paris'], ['id' => 'b', 'text' => 'Rome']], 'answer_key' => ['a'], 'marks' => 2, 'negative_marks' => 0.5, 'position' => 1]),
        'multiple' => $question(['type' => 'multiple_choice', 'prompt' => 'Primes?', 'options' => [['id' => 'a', 'text' => '2'], ['id' => 'b', 'text' => '4'], ['id' => 'c', 'text' => '5']], 'answer_key' => ['a', 'c'], 'marks' => 3, 'position' => 2]),
        'truefalse' => $question(['type' => 'true_false', 'prompt' => 'Water boils at 100°C at sea level.', 'answer_key' => ['true'], 'marks' => 1, 'position' => 3]),
        'essay' => $question(['type' => 'essay', 'prompt' => 'Explain photosynthesis.', 'marks' => 4, 'position' => 4]),
    ];

    return [...$setup, 'teacher' => $teacher, 'outsider' => $outsider, 'studentUser' => $studentUser, 'parentUser' => $parentUser, 'otherUser' => $otherUser, 'otherStudent' => $otherStudent, 'exam' => $exam, 'questions' => $questions];
}

/**
 * Move the clock on as if the user had kept working (so the idle-session timeout does
 * not sign them out).
 */
function travelWhileActive(int $minutes): void
{
    test()->travel($minutes)->minutes();
    session()->forget('security.last_activity_at');
}

/**
 * @param  array<string, mixed>  $setup
 */
function startExam(array $setup): OnlineExamAttempt
{
    test()->actingAs($setup['studentUser'])->post(route('exams.my.start', $setup['exam']))->assertRedirect();

    return OnlineExamAttempt::query()->where('student_id', $setup['student']->id)->latest('id')->firstOrFail();
}

test('teachers set exams only for classes and subjects they teach, and others cannot open them', function () {
    $setup = examSchool();
    $payload = [
        'title' => 'Term 1 science', 'school_class_id' => $setup['section']->school_class_id, 'subject_id' => $setup['subject']->id,
        'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
        'duration_minutes' => 60, 'total_marks' => 50, 'passing_marks' => 20, 'max_attempts' => 1,
    ];

    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.store'), $payload)->assertSessionHasNoErrors();
    expect(OnlineExam::query()->where('title', 'Term 1 science')->sole()->status)->toBe(PublishStatus::Draft);

    $this->actingAs($setup['outsider'])->post(route('academics.online-exams.store'), $payload)->assertSessionHasErrors('subject_id');
    $this->actingAs($setup['outsider'])->get(route('academics.online-exams.show', $setup['exam']))->assertForbidden();
    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.store'), [...$payload, 'duration_minutes' => 300])->assertSessionHasErrors('duration_minutes');
    $this->actingAs($setup['studentUser'])->get(route('academics.online-exams.index'))->assertForbidden();
});

test('an exam is published only when its questions add up to the total marks', function () {
    $setup = examSchool();
    $setup['exam']->update(['status' => PublishStatus::Draft, 'total_marks' => 12]);

    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.publish', $setup['exam']))->assertSessionHasErrors('status');

    $setup['exam']->update(['total_marks' => 10]);
    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.publish', $setup['exam']))->assertSessionHasNoErrors();
    expect($setup['exam']->refresh()->status)->toBe(PublishStatus::Published);
});

test('students reach only exams for their own class, section or name', function () {
    $setup = examSchool();

    $this->actingAs($setup['studentUser'])->get(route('exams.my.show', $setup['exam']))->assertOk();
    $this->actingAs($setup['otherUser'])->get(route('exams.my.show', $setup['exam']))->assertNotFound();
    $this->actingAs($setup['otherUser'])->post(route('exams.my.start', $setup['exam']))->assertNotFound();

    $setup['exam']->students()->sync([Student::factory()->create(['school_id' => $setup['school']->id])->id]);
    Enrollment::factory()->create(['student_id' => $setup['exam']->students()->value('students.id'), 'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);
    $this->actingAs($setup['studentUser'])->get(route('exams.my.show', $setup['exam']))->assertNotFound();

    $setup['exam']->students()->sync([]);
    $setup['exam']->update(['status' => PublishStatus::Draft]);
    $this->actingAs($setup['studentUser'])->get(route('exams.my.show', $setup['exam']))->assertNotFound();
});

test('the exam paper never carries answer keys and the deadline is set by the server', function () {
    $setup = examSchool();
    $attempt = startExam($setup);

    expect($attempt->deadline_at->diffInMinutes($attempt->started_at, true))->toEqualWithDelta(45, 0.1);

    $this->actingAs($setup['studentUser'])->get(route('exams.my.take', [$setup['exam'], $attempt]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('standalone/exam-paper')
            ->has('questions', 4)
            ->missing('questions.0.answer_key')
            ->where('attempt.deadline', $attempt->deadline_at->toIso8601String()));

    $this->actingAs($setup['otherUser'])->get(route('exams.my.take', [$setup['exam'], $attempt]))->assertNotFound();
    $this->actingAs($setup['parentUser'])->get(route('exams.my.take', [$setup['exam'], $attempt]))->assertForbidden();
});

test('autosaved answers are kept, tampered options are dropped and objective questions are marked with negative marking', function () {
    $setup = examSchool();
    $attempt = startExam($setup);
    $q = $setup['questions'];

    $this->actingAs($setup['studentUser'])->putJson(route('exams.my.save', [$setup['exam'], $attempt]), ['answers' => [
        $q['single']->id => 'b',
        $q['multiple']->id => ['a', 'c', 'z'],
        999999 => 'a',
    ]])->assertOk()->assertJsonPath('status', 'in_progress');

    $this->actingAs($setup['studentUser'])->post(route('exams.my.submit', [$setup['exam'], $attempt]), ['answers' => [
        $q['truefalse']->id => 'true',
        $q['essay']->id => 'Plants make food from light.',
    ]])->assertRedirect(route('exams.my.show', $setup['exam']));

    $attempt->refresh();
    $answers = $attempt->answers()->get()->keyBy('online_exam_question_id');

    expect($answers->get($q['single']->id)->marks_awarded)->toBe('-0.50')
        ->and($answers->get($q['multiple']->id)->answer)->toBe(['a', 'c'])
        ->and($answers->get($q['multiple']->id)->marks_awarded)->toBe('3.00')
        ->and($answers->get($q['truefalse']->id)->is_correct)->toBeTrue()
        ->and($answers->get($q['essay']->id)->marks_awarded)->toBeNull()
        ->and($answers)->toHaveCount(4)
        ->and($attempt->status)->toBe(QuizAttemptStatus::Submitted)
        ->and($attempt->score)->toBe('3.50');

    $this->actingAs($setup['studentUser'])->post(route('exams.my.submit', [$setup['exam'], $attempt]), ['answers' => []])->assertSessionHasErrors('exam');
});

test('answers sent after the deadline are ignored but answers saved in time count', function () {
    $setup = examSchool();
    $attempt = startExam($setup);
    $q = $setup['questions'];

    $this->actingAs($setup['studentUser'])->putJson(route('exams.my.save', [$setup['exam'], $attempt]), ['answers' => [$q['single']->id => 'a']])->assertOk();

    travelWhileActive(50);

    $this->actingAs($setup['studentUser'])->putJson(route('exams.my.save', [$setup['exam'], $attempt]), ['answers' => [$q['truefalse']->id => 'true']])
        ->assertOk()->assertJsonPath('status', 'graded');

    $attempt->refresh();
    expect($attempt->submitted_by)->toBe('timeout')
        ->and((float) $attempt->score)->toBe(2.0)
        ->and($attempt->answers()->where('online_exam_question_id', $q['truefalse']->id)->value('answer'))->toBeNull();
});

test('the scheduled command hands in abandoned attempts', function () {
    $setup = examSchool();
    $attempt = startExam($setup);

    $this->travel(2)->hours();
    Artisan::call('exams:close-overdue');

    expect($attempt->refresh()->status)->not->toBe(QuizAttemptStatus::InProgress)
        ->and($attempt->submitted_by)->toBe('timeout');
});

test('students cannot start more attempts than allowed or before the exam opens', function () {
    $setup = examSchool();
    $attempt = startExam($setup);
    $this->actingAs($setup['studentUser'])->post(route('exams.my.submit', [$setup['exam'], $attempt]), ['answers' => []]);

    $this->actingAs($setup['studentUser'])->post(route('exams.my.start', $setup['exam']))->assertSessionHasErrors('exam');

    $setup['exam']->update(['max_attempts' => 2, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(3)]);
    $this->actingAs($setup['studentUser'])->post(route('exams.my.start', $setup['exam']))->assertSessionHasErrors('exam');
    expect(OnlineExamAttempt::query()->count())->toBe(1);
});

test('results stay hidden until marked, approved and published, then students and parents see them', function () {
    $setup = examSchool();
    $q = $setup['questions'];
    $attempt = startExam($setup);
    $this->actingAs($setup['studentUser'])->post(route('exams.my.submit', [$setup['exam'], $attempt]), ['answers' => [
        $q['single']->id => 'a', $q['multiple']->id => ['a', 'c'], $q['truefalse']->id => 'false', $q['essay']->id => 'Light energy.',
    ]]);
    $essay = $attempt->answers()->where('online_exam_question_id', $q['essay']->id)->sole();

    $this->actingAs($setup['studentUser'])->get(route('exams.my.show', $setup['exam']))->assertInertia(fn (Assert $page) => $page->where('result', null));

    travelWhileActive(180);
    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.results.approve', $setup['exam']))->assertSessionHasErrors('results');

    $this->actingAs($setup['teacher'])->put(route('academics.online-exams.attempts.grade', [$setup['exam'], $attempt]), [
        'marks' => [$essay->id => ['marks' => 9, 'feedback' => 'Good start.']],
        'feedback' => 'Well done.',
    ])->assertSessionHasNoErrors();

    $attempt->refresh();
    expect((float) $attempt->score)->toBe(9.0)
        ->and((float) $attempt->percentage)->toBe(90.0)
        ->and($attempt->passed)->toBeTrue()
        ->and($attempt->grade)->not->toBeNull();

    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.results.approve', $setup['exam']))->assertSessionHasNoErrors();
    $this->actingAs($setup['teacher'])->put(route('academics.online-exams.attempts.grade', [$setup['exam'], $attempt]), ['marks' => [$essay->id => ['marks' => 1]]])->assertSessionHasErrors('attempt');
    $this->actingAs($setup['studentUser'])->get(route('exams.my.show', $setup['exam']))->assertInertia(fn (Assert $page) => $page->where('result', null));

    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.results.publish', $setup['exam']))->assertSessionHasNoErrors();

    $this->actingAs($setup['studentUser'])->get(route('exams.my.show', $setup['exam']))->assertInertia(fn (Assert $page) => $page
        ->where('result.score', 9)
        ->where('result.rank', 1)
        ->where('result.questions.0.answer_key', ['a'])
        ->where('result.questions.3.feedback', 'Good start.'));
    $this->actingAs($setup['parentUser'])->get(route('exams.my.show', $setup['exam']))->assertInertia(fn (Assert $page) => $page->where('result.percentage', 90)->where('isExaminee', false));
    $this->actingAs($setup['otherUser'])->get(route('exams.my.show', $setup['exam']))->assertNotFound();
});

test('marks for written answers stay within the question range', function () {
    $setup = examSchool();
    $attempt = startExam($setup);
    $this->actingAs($setup['studentUser'])->post(route('exams.my.submit', [$setup['exam'], $attempt]), ['answers' => [$setup['questions']['essay']->id => 'Answer']]);
    $essay = $attempt->answers()->where('online_exam_question_id', $setup['questions']['essay']->id)->sole();

    $this->actingAs($setup['teacher'])->put(route('academics.online-exams.attempts.grade', [$setup['exam'], $attempt]), ['marks' => [$essay->id => ['marks' => 50]]]);
    expect($essay->refresh()->marks_awarded)->toBe('4.00');

    $this->actingAs($setup['outsider'])->put(route('academics.online-exams.attempts.grade', [$setup['exam'], $attempt]), ['marks' => [$essay->id => ['marks' => 0]]])->assertForbidden();
});

test('questions are frozen once a student starts', function () {
    $setup = examSchool();
    startExam($setup);

    $this->actingAs($setup['teacher'])->put(route('academics.exam-questions.update', $setup['questions']['single']), [
        'type' => 'single_choice', 'prompt' => 'Changed', 'marks' => 2, 'options' => [['text' => 'x', 'correct' => true], ['text' => 'y', 'correct' => false]],
    ])->assertSessionHasErrors('question');
    $this->actingAs($setup['teacher'])->delete(route('academics.online-exams.destroy', $setup['exam']))->assertSessionHasErrors('exam');
});

test('answer keys are encrypted at rest', function () {
    $setup = examSchool();

    $raw = DB::table('online_exam_questions')->where('id', $setup['questions']['single']->id)->value('answer_key');

    expect($raw)->not->toContain('"a"')
        ->and($setup['questions']['single']->refresh()->answer_key)->toBe(['a']);
});

test('question bank items are copied onto a paper and kept to the teacher\'s subjects', function () {
    $setup = examSchool();
    $setup['exam']->update(['status' => PublishStatus::Draft]);
    $bank = [
        'type' => 'true_false', 'prompt' => 'The sun is a star.', 'marks' => 1, 'correct' => 'true',
        'subject_id' => $setup['subject']->id, 'difficulty' => 'easy', 'topic' => 'Space',
    ];

    $this->actingAs($setup['teacher'])->post(route('academics.question-bank.store'), $bank)->assertSessionHasNoErrors();
    $this->actingAs($setup['outsider'])->post(route('academics.question-bank.store'), $bank)->assertSessionHasErrors('subject_id');

    $itemId = (int) DB::table('bank_questions')->value('id');
    $this->actingAs($setup['teacher'])->getJson(route('academics.online-exams.bank', $setup['exam']))->assertOk()->assertJsonPath('0.id', $itemId);
    $this->actingAs($setup['teacher'])->post(route('academics.online-exams.questions.import', $setup['exam']), ['bank_question_ids' => [$itemId]])->assertSessionHasNoErrors();

    $copy = OnlineExamQuestion::query()->where('bank_question_id', $itemId)->sole();
    expect($copy->answer_key)->toBe(['true'])->and($copy->position)->toBe(5);

    $this->actingAs($setup['outsider'])->get(route('academics.question-bank.index'))
        ->assertInertia(fn (Assert $page) => $page->has('questions.data', 0));
});

test('staff see the exam report and can download results; students cannot', function () {
    $setup = examSchool();
    $attempt = startExam($setup);
    $this->actingAs($setup['studentUser'])->post(route('exams.my.submit', [$setup['exam'], $attempt]), ['answers' => [$setup['questions']['single']->id => 'a']]);

    $this->actingAs($setup['teacher'])->get(route('academics.online-exams.report', $setup['exam']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('academics/online-exams/report')
            ->where('report.summary.sat', 1)
            ->where('report.summary.eligible', 1)
            ->has('report.questions', 4));

    $this->actingAs($setup['teacher'])->get(route('academics.online-exams.export', [$setup['exam'], 'format' => 'csv']))->assertOk()->assertDownload();
    $this->actingAs($setup['studentUser'])->get(route('academics.online-exams.report', $setup['exam']))->assertForbidden();
    $this->actingAs($setup['teacher'])->get(route('academics.online-exam-reports.index'))->assertOk();
    $this->actingAs($setup['studentUser'])->get(route('academics.online-exam-reports.index'))->assertForbidden();
});
