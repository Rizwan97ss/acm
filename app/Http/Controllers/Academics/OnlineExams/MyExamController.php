<?php

namespace App\Http\Controllers\Academics\OnlineExams;

use App\Enums\OnlineExamState;
use App\Enums\QuizAttemptStatus;
use App\Models\AcademicYear;
use App\Models\OnlineExam;
use App\Models\OnlineExamAnswer;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamQuestion;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\Exams\OnlineExamReports;
use App\Services\Academics\Exams\OnlineExamResults;
use App\Services\Academics\Exams\OnlineExamScope;
use App\Services\Academics\Exams\OnlineExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Students sit their exams here; students and parents see published results.
 *
 * Every request re-checks that the exam is published, belongs to the student's current
 * enrolment (class, section or named students) and, for attempts, that the attempt is
 * the student's own. Answer keys and marks are only sent once results are published.
 */
class MyExamController extends OnlineExamController
{
    public function __construct(
        OnlineExamScope $scope,
        private OnlineExamService $exams,
        private OnlineExamResults $results,
        private OnlineExamReports $reports,
    ) {
        parent::__construct($scope);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermission('online_exams.view'), 403);
        $studentId = $request->integer('student') ?: null;
        $student = $this->scope->learner($user, $studentId);
        abort_if($student === null, 403);
        $year = $this->currentYear();

        $exams = $this->scope->examsFor($student, $year)
            ->with(['subject:id,name,color'])
            ->withCount('questions')
            ->orderBy('starts_at')
            ->get();
        $attempts = OnlineExamAttempt::query()->where('student_id', $student->id)->whereIn('online_exam_id', $exams->pluck('id'))->get()->groupBy('online_exam_id');

        return Inertia::render('exams/index', [
            'student' => ['id' => $student->id, 'name' => $student->full_name],
            'children' => $this->children($user),
            'isExaminee' => $this->scope->examinee($user)?->id === $student->id,
            'exams' => $exams->map(fn (OnlineExam $exam): array => $this->card($exam, $attempts->get($exam->id, collect())))->values(),
            'history' => $this->reports->studentHistory($student->id, $year),
        ]);
    }

    /**
     * The exam lobby before starting, and the result afterwards.
     */
    public function show(Request $request, OnlineExam $onlineExam): Response|RedirectResponse
    {
        $user = $request->user();
        $student = $this->student($request, $onlineExam, allowParents: true);
        $exam = $onlineExam;
        $attempts = OnlineExamAttempt::query()->where('online_exam_id', $exam->id)->where('student_id', $student->id)->orderBy('attempt_no')->get()
            ->map(fn (OnlineExamAttempt $attempt): OnlineExamAttempt => $this->exams->expireIfOverdue($attempt->setRelation('exam', $exam)));
        $isExaminee = $this->scope->examinee($user)?->id === $student->id;
        $current = $attempts->first(fn (OnlineExamAttempt $attempt): bool => $attempt->isInProgress());

        if ($current !== null && $isExaminee) {
            return to_route('exams.my.take', [$exam, $current]);
        }

        return Inertia::render('exams/show', [
            'exam' => [
                ...$this->card($exam, $attempts),
                'instructions' => $exam->instructions,
                'negative_marking' => $exam->negative_marking,
            ],
            'student' => ['id' => $student->id, 'name' => $student->full_name],
            'isExaminee' => $isExaminee,
            'result' => $exam->resultsPublished() ? $this->result($exam, $student) : null,
        ]);
    }

    public function start(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $student = $this->student($request, $onlineExam);
        $attempt = $this->exams->start($student, $onlineExam, $request->ip());

        return to_route('exams.my.take', [$onlineExam, $attempt]);
    }

    /**
     * The exam paper. Questions and options come in the order fixed for this attempt; no
     * answer keys are sent.
     */
    public function take(Request $request, OnlineExam $onlineExam, OnlineExamAttempt $attempt): Response|RedirectResponse
    {
        $student = $this->student($request, $onlineExam);
        $attempt = $this->own($attempt, $onlineExam, $student);
        $attempt = $this->exams->expireIfOverdue($attempt->setRelation('exam', $onlineExam));

        if (! $attempt->isInProgress()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This attempt has been handed in.')]);

            return to_route('exams.my.show', $onlineExam);
        }

        $questions = OnlineExamQuestion::query()->whereIn('id', array_column($attempt->layout, 'question'))->get()->keyBy('id');
        $saved = OnlineExamAnswer::query()->where('online_exam_attempt_id', $attempt->id)->get()->keyBy('online_exam_question_id');

        return Inertia::render('standalone/exam-paper', [
            'exam' => [
                'id' => $onlineExam->id,
                'title' => $onlineExam->title,
                'subject' => $onlineExam->subject()->value('name'),
                'instructions' => $onlineExam->instructions,
                'total_marks' => (float) $onlineExam->total_marks,
                'negative_marking' => $onlineExam->negative_marking,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'attempt_no' => $attempt->attempt_no,
                'deadline' => $attempt->deadline_at->toIso8601String(),
                'server_now' => now()->toIso8601String(),
                'last_saved_at' => $attempt->last_saved_at?->toIso8601String(),
            ],
            'student' => $student->full_name,
            'questions' => array_values(array_filter(array_map(function (array $slot) use ($questions, $saved): ?array {
                /** @var OnlineExamQuestion|null $question */
                $question = $questions->get($slot['question']);

                if ($question === null) {
                    return null;
                }

                $options = collect($question->options ?? [])->keyBy('id');

                return [
                    'id' => $question->id,
                    'type' => $question->type->value,
                    'prompt' => $question->prompt,
                    'marks' => (float) $question->marks,
                    'negative_marks' => (float) $question->negative_marks,
                    'options' => array_values(array_filter(array_map(fn (string $id): ?array => $options->get($id), $slot['options']))),
                    'answer' => $saved->get($question->id)?->answer,
                ];
            }, $attempt->layout))),
        ]);
    }

    /**
     * Autosave (JSON). Returns the server's view of the attempt so the page can follow it.
     */
    public function save(Request $request, OnlineExam $onlineExam, OnlineExamAttempt $attempt): JsonResponse
    {
        $student = $this->student($request, $onlineExam);
        $attempt = $this->own($attempt, $onlineExam, $student);
        $answers = $this->validatedAnswers($request);

        $attempt = $this->exams->save($attempt->setRelation('exam', $onlineExam), $answers);

        return response()->json([
            'status' => $attempt->status->value,
            'saved_at' => $attempt->last_saved_at?->toIso8601String(),
            'deadline' => $attempt->deadline_at->toIso8601String(),
            'server_now' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, OnlineExam $onlineExam, OnlineExamAttempt $attempt): RedirectResponse
    {
        $student = $this->student($request, $onlineExam);
        $attempt = $this->own($attempt, $onlineExam, $student);
        $answers = $this->validatedAnswers($request);

        // The server's clock decides whether this was a timed-out hand-in, never the browser.
        $attempt = $this->exams->submit($attempt->setRelation('exam', $onlineExam), $answers, now()->gte($attempt->deadline_at) ? 'timeout' : 'student');

        Inertia::flash('toast', ['type' => 'success', 'message' => $attempt->submitted_by === 'timeout'
            ? __('Time was up, so your exam was handed in with the answers saved in time.')
            : __('Exam handed in. Your results will appear here once your teacher publishes them.')]);

        return to_route('exams.my.show', $onlineExam);
    }

    /* --------------------------------------------------------------- helpers */

    /**
     * The student this request is for, after checking the exam is theirs to see.
     */
    private function student(Request $request, OnlineExam $exam, bool $allowParents = false): Student
    {
        $user = $request->user();
        abort_unless($user->hasPermission('online_exams.view'), 403);

        $student = $allowParents
            ? $this->scope->learner($user, $request->integer('student') ?: null)
            : $this->scope->examinee($user);

        abort_if($student === null, 403);

        $year = AcademicYear::query()->current()->first();
        abort_if($year === null || $exam->academic_year_id !== $year->id, 404);
        abort_unless($this->scope->examsFor($student, $year)->whereKey($exam->id)->exists(), 404);

        return $student;
    }

    private function own(OnlineExamAttempt $attempt, OnlineExam $exam, Student $student): OnlineExamAttempt
    {
        abort_unless($attempt->online_exam_id === $exam->id && $attempt->student_id === $student->id, 404);

        return $attempt;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function validatedAnswers(Request $request): array
    {
        $validated = $request->validate([
            'answers' => ['nullable', 'array', 'max:200'],
            'answers.*' => ['nullable'],
            'answers.*.*' => ['nullable', 'string', 'max:20'],
        ]);

        return (array) ($validated['answers'] ?? []);
    }

    /**
     * @param  Collection<int, OnlineExamAttempt>  $attempts
     * @return array<string, mixed>
     */
    private function card(OnlineExam $exam, Collection $attempts): array
    {
        $exam->loadMissing('subject:id,name,color');
        $state = $exam->state();
        $used = $attempts->count();
        $inProgress = $attempts->first(fn (OnlineExamAttempt $attempt): bool => $attempt->isInProgress());

        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'subject' => $exam->subject->name,
            'subject_color' => $exam->subject->color,
            'starts_at' => $exam->starts_at->toIso8601String(),
            'ends_at' => $exam->ends_at->toIso8601String(),
            'duration_minutes' => $exam->duration_minutes,
            'total_marks' => (float) $exam->total_marks,
            'passing_marks' => (float) $exam->passing_marks,
            'questions_count' => (int) ($exam->getAttribute('questions_count') ?? $exam->questions()->count()),
            'max_attempts' => $exam->max_attempts,
            'attempts_used' => $used,
            'state' => $state->value,
            'state_label' => $state->label(),
            'can_start' => $state === OnlineExamState::Active && ($inProgress !== null || $used < $exam->max_attempts),
            'in_progress' => $inProgress !== null,
            'handed_in' => $attempts->contains(fn (OnlineExamAttempt $attempt): bool => ! $attempt->isInProgress()),
            'results_published' => $exam->resultsPublished(),
        ];
    }

    /**
     * The published result: the counted attempt with per-question review, answer key and
     * feedback, and the rank if the exam shows ranks.
     *
     * @return array<string, mixed>|null
     */
    private function result(OnlineExam $exam, Student $student): ?array
    {
        $counted = $this->results->countedAttempts($exam);
        /** @var OnlineExamAttempt|null $attempt */
        $attempt = $counted->get($student->id);

        if ($attempt === null || $attempt->status !== QuizAttemptStatus::Graded) {
            return null;
        }

        $answers = OnlineExamAnswer::query()->where('online_exam_attempt_id', $attempt->id)->get()->keyBy('online_exam_question_id');
        $questions = OnlineExamQuestion::query()->where('online_exam_id', $exam->id)->get()->keyBy('id');
        $ranks = $exam->show_rank ? $this->results->ranks($counted) : [];

        return [
            'score' => (float) $attempt->score,
            'max_score' => (float) $attempt->max_score,
            'percentage' => (float) $attempt->percentage,
            'grade' => $attempt->grade,
            'passed' => (bool) $attempt->passed,
            'feedback' => $attempt->feedback,
            'rank' => $ranks[$student->id] ?? null,
            'ranked_out_of' => $exam->show_rank ? count($ranks) : null,
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'questions' => array_values(array_filter(array_map(function (array $slot, int $index) use ($questions, $answers): ?array {
                /** @var OnlineExamQuestion|null $question */
                $question = $questions->get($slot['question']);

                if ($question === null) {
                    return null;
                }

                $answer = $answers->get($question->id);

                return [
                    'number' => $index + 1,
                    'type' => $question->type->value,
                    'prompt' => $question->prompt,
                    'marks' => (float) $question->marks,
                    'options' => $question->options ?? [],
                    'answer_key' => $question->answer_key ?? [],
                    'explanation' => $question->explanation,
                    'answer' => $answer?->answer,
                    'is_correct' => $answer?->is_correct,
                    'marks_awarded' => $answer?->marks_awarded !== null ? (float) $answer->marks_awarded : null,
                    'feedback' => $answer?->feedback,
                ];
            }, $attempt->layout, array_keys($attempt->layout)))),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function children(User $user): array
    {
        $ids = $this->scope->childIds($user);

        if (count($ids) < 2) {
            return [];
        }

        return array_values(Student::query()->whereIn('id', $ids)->orderBy('first_name')->get()
            ->map(fn (Student $student): array => ['id' => $student->id, 'name' => $student->full_name])->all());
    }
}
