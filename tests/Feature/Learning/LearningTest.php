<?php

use App\Enums\LiveClassStatus;
use App\Enums\MaterialKind;
use App\Enums\PublishStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\SettingScope;
use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\LiveClass;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\LearningUpdate;
use App\Services\Learning\LearningInsights;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * academicSchool() plus a class teacher who may build learning content, a student with a
 * portal login in section A, a classmate in section B of the same class, and a student in
 * another class.
 *
 * @return array<string, mixed>
 */
function learningSchool(): array
{
    $setup = academicSchool();
    $school = $setup['school'];
    $year = $setup['year'];
    $class = $setup['section']->schoolClass;

    $teacher = userWithPermissions([
        'courses.view', 'courses.create', 'courses.edit', 'courses.delete',
        'live_classes.view', 'live_classes.create', 'live_classes.edit', 'live_classes.delete',
        'quizzes.view', 'quizzes.create', 'quizzes.edit', 'quizzes.delete',
        'assignments.view', 'assignments.create', 'assignments.edit', 'assignments.delete',
    ], $school);
    $setup['section']->update(['class_teacher_id' => $teacher->id]);

    $portal = ['courses.view', 'live_classes.view', 'quizzes.view', 'assignments.view'];
    $makeStudent = function (Section $section) use ($school, $year, $portal): array {
        $user = userWithPermissions($portal, $school);
        $student = Student::factory()->create(['school_id' => $school->id, 'user_id' => $user->id]);
        Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $section->id, 'academic_year_id' => $year->id]);

        return [$student, $user];
    };

    [$student, $studentUser] = $makeStudent($setup['section']);
    $sectionB = Section::factory()->create(['school_class_id' => $class->id, 'name' => 'B']);
    [$classmate, $classmateUser] = $makeStudent($sectionB);
    $otherClass = SchoolClass::factory()->create(['school_id' => $school->id]);
    $otherClass->subjects()->attach($setup['subject']->id, ['periods_per_week' => 3]);
    [$outsider, $outsiderUser] = $makeStudent(Section::factory()->create(['school_class_id' => $otherClass->id, 'name' => 'A']));

    return [...$setup, 'class' => $class, 'teacher' => $teacher, 'student' => $student, 'studentUser' => $studentUser,
        'sectionB' => $sectionB, 'classmate' => $classmate, 'classmateUser' => $classmateUser,
        'otherClass' => $otherClass, 'outsider' => $outsider, 'outsiderUser' => $outsiderUser];
}

/**
 * A published course with one published lesson for the whole class.
 *
 * @param  array<string, mixed>  $setup
 * @return array{0: Course, 1: Lesson}
 */
function publishedLesson(array $setup, array $courseOverrides = [], array $lessonOverrides = []): array
{
    $course = Course::query()->create([
        'school_id' => $setup['school']->id,
        'academic_year_id' => $setup['year']->id,
        'school_class_id' => $setup['class']->id,
        'subject_id' => $setup['subject']->id,
        'title' => 'Fractions',
        'status' => PublishStatus::Published,
        'created_by' => $setup['teacher']->id,
        ...$courseOverrides,
    ]);
    $chapter = CourseChapter::query()->create(['school_id' => $course->school_id, 'course_id' => $course->id, 'title' => 'Basics', 'position' => 1]);
    $lesson = Lesson::query()->create([
        'school_id' => $course->school_id,
        'course_id' => $course->id,
        'course_chapter_id' => $chapter->id,
        'title' => 'What is a fraction?',
        'status' => PublishStatus::Published,
        'position' => 1,
        'created_by' => $setup['teacher']->id,
        ...$lessonOverrides,
    ]);

    return [$course, $lesson];
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

/* ------------------------------------------------------------ teacher scope */

test('teachers create courses only for classes and subjects they teach', function () {
    $setup = learningSchool();
    $otherSubject = Subject::factory()->create(['school_id' => $setup['school']->id]);
    $setup['otherClass']->subjects()->attach($otherSubject->id, ['periods_per_week' => 2]);

    $this->actingAs($setup['teacher'])->post(route('learning.courses.store'), [
        'school_class_id' => $setup['class']->id, 'subject_id' => $setup['subject']->id,
        'title' => 'Fractions', 'status' => 'draft',
    ])->assertRedirect();

    expect(Course::query()->sole())->title->toBe('Fractions')
        ->and(CourseChapter::query()->count())->toBe(1);

    $this->actingAs($setup['teacher'])->post(route('learning.courses.store'), [
        'school_class_id' => $setup['otherClass']->id, 'subject_id' => $otherSubject->id,
        'title' => 'Not mine', 'status' => 'draft',
    ])->assertSessionHasErrors('subject_id');

    expect(Course::query()->count())->toBe(1);
});

test('a subject not taught in the class or a section of another class is rejected', function () {
    $setup = learningSchool();
    $foreignSubject = Subject::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs($setup['teacher'])->post(route('learning.courses.store'), [
        'school_class_id' => $setup['class']->id, 'section_id' => Section::query()->where('school_class_id', $setup['otherClass']->id)->value('id'),
        'subject_id' => $foreignSubject->id, 'title' => 'Bad', 'status' => 'draft',
    ])->assertSessionHasErrors(['section_id', 'subject_id']);
});

test('chosen students must belong to the course audience', function () {
    $setup = learningSchool();

    $this->actingAs($setup['teacher'])->post(route('learning.courses.store'), [
        'school_class_id' => $setup['class']->id, 'subject_id' => $setup['subject']->id,
        'title' => 'Extension', 'status' => 'draft', 'student_ids' => [$setup['outsider']->id],
    ])->assertSessionHasErrors('student_ids');
});

/* --------------------------------------------------------- student visibility */

test('students see published courses for their class, across sections when none is set', function () {
    $setup = learningSchool();
    [$course] = publishedLesson($setup);
    publishedLesson($setup, ['title' => 'Draft course', 'status' => PublishStatus::Draft]);
    publishedLesson($setup, ['title' => 'Section B only', 'section_id' => $setup['sectionB']->id]);

    $titles = fn (User $user) => collect($this->actingAs($user)->get(route('learn.index'))->assertOk()->viewData('page')['props']['courses'])->pluck('title')->all();

    expect($titles($setup['studentUser']))->toBe(['Fractions'])
        ->and($titles($setup['classmateUser']))->toEqualCanonicalizing(['Fractions', 'Section B only'])
        ->and($titles($setup['outsiderUser']))->toBe([]);

    $this->actingAs($setup['outsiderUser'])->get(route('learn.courses.show', $course))->assertNotFound();
});

test('courses for chosen students are hidden from everyone else', function () {
    $setup = learningSchool();
    [$course, $lesson] = publishedLesson($setup, ['title' => 'Extension work']);
    $course->students()->attach($setup['classmate']->id);

    $this->actingAs($setup['classmateUser'])->get(route('learn.lessons.show', $lesson))->assertOk();
    $this->actingAs($setup['studentUser'])->get(route('learn.lessons.show', $lesson))->assertNotFound();
});

test('lessons scheduled for later stay hidden until their publish time', function () {
    $setup = learningSchool();
    [, $lesson] = publishedLesson($setup, [], ['publish_at' => now()->addDay()]);

    $this->actingAs($setup['studentUser'])->get(route('learn.lessons.show', $lesson))->assertNotFound();

    $this->travel(2)->days();
    $this->flushSession();

    $this->actingAs($setup['studentUser'])->get(route('learn.lessons.show', $lesson))->assertOk();
});

test('another school never sees the content', function () {
    $setup = learningSchool();
    [$course] = publishedLesson($setup);
    $stranger = userWithPermissions(['courses.view', 'courses.create', 'courses.edit'], School::factory()->create());

    $this->actingAs($stranger)->get(route('learning.courses.show', $course))->assertNotFound();
});

/* ------------------------------------------------------------- progress */

test('opening, completing and bookmarking a lesson is tracked', function () {
    $setup = learningSchool();
    [$course, $lesson] = publishedLesson($setup);

    $this->actingAs($setup['studentUser'])->get(route('learn.lessons.show', $lesson))->assertOk();
    $this->actingAs($setup['studentUser'])->get(route('learn.lessons.show', $lesson))->assertOk();
    $this->actingAs($setup['studentUser'])->post(route('learn.lessons.complete', $lesson), ['completed' => true])->assertRedirect();
    $this->actingAs($setup['studentUser'])->post(route('learn.lessons.bookmark', $lesson))->assertRedirect();

    $progress = LessonProgress::query()->sole();
    expect($progress->views)->toBe(3)->and($progress->completed_at)->not->toBeNull();

    $this->actingAs($setup['studentUser'])->get(route('learn.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.0.progress.percent', 100)
            ->where('bookmarks.0.lesson_id', $lesson->id)
            ->where('recent.0.completed', true));

    $this->actingAs($setup['teacher'])->get(route('learning.courses.show', $course))
        ->assertInertia(fn (Assert $page) => $page->where('chapters.0.lessons.0.completed_count', 1));
});

test('parents follow their child’s learning read-only', function () {
    $setup = learningSchool();
    [, $lesson] = publishedLesson($setup);
    $parent = userWithPermissions(['courses.view'], $setup['school']);
    $guardian = Guardian::factory()->create(['school_id' => $setup['school']->id, 'user_id' => $parent->id]);
    $setup['student']->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true, 'is_emergency_contact' => true]);

    $this->actingAs($parent)->get(route('learn.lessons.show', $lesson))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('learner.is_self', false));
    $this->actingAs($parent)->post(route('learn.lessons.complete', $lesson), ['completed' => true])->assertForbidden();

    expect(LessonProgress::query()->count())->toBe(0);
});

/* ------------------------------------------------------------- materials */

test('materials are stored privately and served only through signed, access-checked links', function () {
    $setup = learningSchool();
    [, $lesson] = publishedLesson($setup);

    $this->actingAs($setup['teacher'])->post(route('learning.lessons.materials.store', $lesson), [
        'title' => 'Worksheet', 'is_downloadable' => false,
        'file' => UploadedFile::fake()->create('worksheet.pdf', 120, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $material = LessonMaterial::query()->sole();
    expect($material->kind)->toBe(MaterialKind::Pdf)
        ->and($material->path)->toStartWith("lms/{$setup['school']->id}/");
    Storage::disk('local')->assertExists($material->path);

    $inline = URL::temporarySignedRoute('learning.files.material', now()->addMinutes(5), ['material' => $material->id, 'mode' => 'inline']);
    $download = URL::temporarySignedRoute('learning.files.material', now()->addMinutes(5), ['material' => $material->id, 'mode' => 'download']);

    $this->actingAs($setup['studentUser'])->get($inline)->assertOk();
    $this->actingAs($setup['studentUser'])->get($download)->assertForbidden();
    $this->actingAs($setup['studentUser'])->get(route('learning.files.material', ['material' => $material->id, 'mode' => 'inline']))->assertForbidden();
    $this->actingAs($setup['outsiderUser'])->get($inline)->assertNotFound();
});

test('dangerous file types and a full storage quota are refused', function () {
    $setup = learningSchool();
    [, $lesson] = publishedLesson($setup);

    $this->actingAs($setup['teacher'])->post(route('learning.lessons.materials.store', $lesson), [
        'title' => 'Script', 'file' => UploadedFile::fake()->create('evil.html', 1, 'text/html'),
    ])->assertSessionHasErrors('file');

    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['lms.storage_quota_mb' => 1]);

    $this->actingAs($setup['teacher'])->post(route('learning.lessons.materials.store', $lesson), [
        'title' => 'Big video', 'file' => UploadedFile::fake()->create('lesson.mp4', 2048, 'video/mp4'),
    ])->assertSessionHasErrors('file');

    expect(LessonMaterial::query()->count())->toBe(0);
});

test('publishing a lesson notifies its students once', function () {
    $setup = learningSchool();
    [, $lesson] = publishedLesson($setup, [], ['status' => PublishStatus::Draft]);

    $payload = ['title' => $lesson->title, 'status' => 'published'];
    $this->actingAs($setup['teacher'])->put(route('learning.lessons.update', $lesson), $payload)->assertSessionHasNoErrors();
    $this->actingAs($setup['teacher'])->put(route('learning.lessons.update', $lesson), $payload);

    Notification::assertSentToTimes($setup['studentUser'], LearningUpdate::class, 1);
    Notification::assertSentTo($setup['classmateUser'], LearningUpdate::class);
    Notification::assertNotSentTo($setup['outsiderUser'], LearningUpdate::class);
});

/* ------------------------------------------------------------ live classes */

test('live classes notify students and the link is only handed out in the join window', function () {
    $setup = learningSchool();

    $this->actingAs($setup['teacher'])->post(route('learning.live-classes.store'), [
        'school_class_id' => $setup['class']->id, 'subject_id' => $setup['subject']->id,
        'title' => 'Revision', 'provider' => 'google_meet', 'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addHour()->toIso8601String(), 'duration_minutes' => 40,
    ])->assertSessionHasNoErrors();

    $liveClass = LiveClass::query()->sole();
    expect($liveClass->getRawOriginal('meeting_url'))->not->toContain('meet.google.com');
    Notification::assertSentTo($setup['studentUser'], LearningUpdate::class);

    $this->actingAs($setup['studentUser'])->get(route('learn.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('liveClasses.0.meeting_url')->where('liveClasses.0.title', 'Revision'));
    $this->actingAs($setup['studentUser'])->get(route('learn.live-classes.join', $liveClass))->assertRedirect();
    $this->actingAs($setup['outsiderUser'])->get(route('learn.live-classes.join', $liveClass))->assertNotFound();

    $this->travel(55)->minutes();
    $this->flushSession();

    $this->actingAs($setup['studentUser'])->get(route('learn.live-classes.join', $liveClass))->assertRedirect('https://meet.google.com/abc-defg-hij');
});

test('reminders go out once, shortly before the class', function () {
    $setup = learningSchool();
    $liveClass = LiveClass::query()->create([
        'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['class']->id,
        'subject_id' => $setup['subject']->id, 'title' => 'Algebra', 'provider' => 'zoom', 'meeting_url' => 'https://zoom.us/j/1',
        'starts_at' => now()->addMinutes(10), 'duration_minutes' => 30, 'status' => LiveClassStatus::Scheduled,
    ]);

    $this->artisan('lms:dispatch')->assertSuccessful();
    $this->artisan('lms:dispatch')->assertSuccessful();

    Notification::assertSentToTimes($setup['studentUser'], LearningUpdate::class, 1);
    expect($liveClass->refresh()->reminded_at)->not->toBeNull();
});

/* ----------------------------------------------------------------- quizzes */

test('objective answers are marked automatically and essays wait for the teacher', function () {
    $setup = learningSchool();
    $this->actingAs($setup['teacher'])->post(route('learning.quizzes.store'), [
        'school_class_id' => $setup['class']->id, 'subject_id' => $setup['subject']->id, 'type' => 'quiz',
        'title' => 'Check-in', 'max_attempts' => 1, 'status' => 'draft',
    ])->assertRedirect();
    $quiz = Quiz::query()->sole();

    $add = fn (array $question) => $this->actingAs($setup['teacher'])->post(route('learning.quizzes.questions.store', $quiz), $question)->assertSessionHasNoErrors();
    $add(['type' => 'single_choice', 'prompt' => '1/2 + 1/2 = ?', 'marks' => 2, 'options' => [['text' => '1', 'correct' => true], ['text' => '2', 'correct' => false]]]);
    $add(['type' => 'true_false', 'prompt' => '3/4 > 1/2', 'marks' => 1, 'correct' => 'true']);
    $add(['type' => 'short_answer', 'prompt' => 'Top number?', 'marks' => 1, 'accepted_answers' => ['Numerator']]);
    $add(['type' => 'essay', 'prompt' => 'Explain halves.', 'marks' => 5]);

    $this->actingAs($setup['teacher'])->put(route('learning.quizzes.update', $quiz), [
        'school_class_id' => $setup['class']->id, 'subject_id' => $setup['subject']->id, 'type' => 'quiz',
        'title' => 'Check-in', 'max_attempts' => 1, 'status' => 'published',
    ])->assertSessionHasNoErrors();
    Notification::assertSentTo($setup['studentUser'], LearningUpdate::class);

    $this->actingAs($setup['studentUser'])->get(route('learn.quizzes.show', $quiz))
        ->assertInertia(fn (Assert $page) => $page->where('questions', []));

    $this->actingAs($setup['studentUser'])->post(route('learn.quizzes.start', $quiz))->assertRedirect();
    $attempt = QuizAttempt::query()->sole();

    $this->actingAs($setup['studentUser'])->get(route('learn.quizzes.show', $quiz))
        ->assertInertia(fn (Assert $page) => $page->has('questions', 4)->where('questions.0.answer_key', null));

    $questions = $quiz->questions()->get();
    $this->actingAs($setup['studentUser'])->post(route('learn.quizzes.submit', [$quiz, $attempt]), ['answers' => [
        $questions[0]->id => 'a',
        $questions[1]->id => 'true',
        $questions[2]->id => ' numerator ',
        $questions[3]->id => 'Two equal parts.',
    ]])->assertRedirect();

    $attempt->refresh();
    expect($attempt->status)->toBe(QuizAttemptStatus::Submitted)
        ->and((float) $attempt->score)->toBe(4.0)
        ->and((float) $attempt->max_score)->toBe(9.0);

    $this->actingAs($setup['studentUser'])->post(route('learn.quizzes.start', $quiz))->assertSessionHasErrors('quiz');

    $essay = $attempt->answers()->where('quiz_question_id', $questions[3]->id)->sole();
    $this->actingAs($setup['teacher'])->put(route('learning.quizzes.attempts.grade', [$quiz, $attempt]), [
        'marks' => [$essay->id => ['marks' => 4, 'feedback' => 'Good']], 'feedback' => 'Well done',
    ])->assertRedirect();

    $attempt->refresh();
    expect($attempt->status)->toBe(QuizAttemptStatus::Graded)->and((float) $attempt->score)->toBe(8.0);
    Notification::assertSentToTimes($setup['studentUser'], LearningUpdate::class, 2);

    $this->actingAs($setup['teacher'])->post(route('learning.quizzes.questions.store', $quiz), ['type' => 'essay', 'prompt' => 'Late', 'marks' => 1])
        ->assertSessionHasErrors('question');
});

test('answers handed in after the time limit score nothing', function () {
    $setup = learningSchool();
    $quiz = Quiz::query()->create([
        'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['class']->id,
        'subject_id' => $setup['subject']->id, 'type' => 'test', 'title' => 'Timed', 'time_limit_minutes' => 5,
        'max_attempts' => 1, 'status' => PublishStatus::Published, 'created_by' => $setup['teacher']->id,
    ]);
    $question = $quiz->questions()->create(['school_id' => $quiz->school_id, 'type' => 'true_false', 'prompt' => 'Sky is blue', 'marks' => 1, 'answer_key' => ['true'], 'position' => 1]);

    $this->actingAs($setup['studentUser'])->post(route('learn.quizzes.start', $quiz));
    $attempt = QuizAttempt::query()->sole();

    $this->travel(10)->minutes();
    $this->flushSession();

    $this->actingAs($setup['studentUser'])->post(route('learn.quizzes.submit', [$quiz, $attempt]), ['answers' => [$question->id => 'true']]);

    expect((float) $attempt->refresh()->score)->toBe(0.0)->and($attempt->status)->toBe(QuizAttemptStatus::Graded);
});

/* ----------------------------------------------------------- assignments */

test('assignments can carry files, target chosen students and take file hand-ins', function () {
    $setup = learningSchool();
    Section::query()->whereKey($setup['section']->id)->update(['class_teacher_id' => $setup['teacher']->id]);
    $classmateInA = Student::factory()->create(['school_id' => $setup['school']->id, 'user_id' => ($otherUser = userWithPermissions(['assignments.view'], $setup['school']))->id]);
    Enrollment::factory()->create(['student_id' => $classmateInA->id, 'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);

    $this->actingAs($setup['teacher'])->post(route('academics.assignments.store'), [
        'section_id' => $setup['section']->id, 'subject_id' => $setup['subject']->id, 'type' => 'homework',
        'title' => 'Worksheet 3', 'description' => 'Complete it.', 'due_on' => now()->addWeek()->toDateString(),
        'student_ids' => [$setup['student']->id],
        'attachments' => [UploadedFile::fake()->create('sheet.pdf', 50, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $assignment = Assignment::query()->sole();
    expect($assignment->students()->pluck('students.id')->all())->toBe([$setup['student']->id])
        ->and(AssignmentFile::query()->whereNull('assignment_submission_id')->count())->toBe(1);

    $this->actingAs($otherUser)->get(route('academics.assignments.show', $assignment))->assertForbidden();

    $this->actingAs($setup['studentUser'])->post(route('academics.assignments.submit', $assignment), [
        'files' => [UploadedFile::fake()->create('answers.pdf', 40, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    expect(AssignmentFile::query()->whereNotNull('assignment_submission_id')->count())->toBe(1);

    $submission = $assignment->submissions()->sole();
    $this->actingAs($setup['teacher'])->put(route('academics.assignments.grade', [$assignment, $submission]), ['feedback' => 'Neat work'])->assertRedirect();
    Notification::assertSentTo($setup['studentUser'], LearningUpdate::class);
});

/* ------------------------------------------------------- switches and views */

test('the learning portal can be switched off for a school', function () {
    $setup = learningSchool();
    app(SettingsService::class)->put(SettingScope::School, $setup['school']->id, ['lms.enabled' => false]);

    $this->actingAs($setup['studentUser'])->get(route('learn.index'))->assertRedirect(route('dashboard'));
    $this->actingAs($setup['teacher'])->get(route('learning.courses.index'))->assertRedirect(route('dashboard'));
    $this->actingAs($setup['studentUser'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('features.learning', false));
});

test('the learning overview works even when the analytics service is unavailable', function () {
    $setup = learningSchool();
    publishedLesson($setup);
    config(['services.analytics.url' => null]);

    $this->actingAs($setup['teacher'])->get(route('learning.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('learning/dashboard')
            ->where('stats.published_courses', 1)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('courseProgress.0.title', 'Fractions')
                ->where('engagement.available', false)));
});

test('the engagement dataset counts lessons, completion and homework per student', function () {
    $setup = learningSchool();
    [, $lesson] = publishedLesson($setup);
    LessonProgress::query()->create(['school_id' => $setup['school']->id, 'lesson_id' => $lesson->id, 'student_id' => $setup['student']->id, 'first_viewed_at' => now(), 'last_viewed_at' => now(), 'completed_at' => now()]);

    $rows = collect(app(LearningInsights::class)->engagementDataset($setup['year'], null))->keyBy('student_id');

    expect($rows[$setup['student']->id])->toMatchArray(['lessons_available' => 1, 'lessons_completed' => 1, 'days_since_active' => 0])
        ->and($rows[$setup['outsider']->id])->toMatchArray(['lessons_available' => 0, 'lessons_completed' => 0, 'days_since_active' => null])
        ->and(array_key_exists('name', $rows[$setup['student']->id]))->toBeFalse();
});
