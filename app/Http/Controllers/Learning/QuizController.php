<?php

namespace App\Http\Controllers\Learning;

use App\Enums\PublishStatus;
use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizType;
use App\Http\Requests\Learning\QuizQuestionRequest;
use App\Http\Requests\Learning\QuizRequest;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LearningScope;
use App\Services\Learning\QuizService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quizzes and tests: settings, the question builder, results and marking.
 */
class QuizController extends LearningController
{
    public function __construct(
        LearningScope $scope,
        private LearningNotifier $notifier,
        private QuizService $quizzes,
    ) {
        parent::__construct($scope);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('quizzes.create');

        $user = $request->user();
        $year = $this->currentYear();

        $quizzes = Quiz::query()
            ->with(['schoolClass:id,name', 'section:id,name', 'subject:id,name,color'])
            ->withCount(['questions', 'attempts', 'attempts as to_mark_count' => fn (Builder $query) => $query->where('status', QuizAttemptStatus::Submitted)])
            ->where('academic_year_id', $year->id)
            ->when(! $this->scope->supervises($user, 'quizzes'), fn (Builder $query) => $this->limitToTeacher($query, $user, $year))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('learning/quizzes/index', [
            'quizzes' => [
                'data' => $quizzes->getCollection()->map(fn (Quiz $quiz): array => [
                    ...$this->present($quiz),
                    'questions_count' => (int) $quiz->getAttribute('questions_count'),
                    'attempts_count' => (int) $quiz->getAttribute('attempts_count'),
                    'to_mark_count' => (int) $quiz->getAttribute('to_mark_count'),
                ])->values(),
                'meta' => $this->meta($quizzes),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('quizzes.create');

        return Inertia::render('learning/quizzes/form', [...$this->formOptions($request->user()), 'quiz' => null]);
    }

    public function store(QuizRequest $request): RedirectResponse
    {
        $year = $this->currentYear();
        $this->authorizeAudience($request, $year);

        $quiz = Quiz::query()->create([
            ...$request->validated(),
            'status' => PublishStatus::Draft,
            'academic_year_id' => $year->id,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz created. Add questions, then publish it.')]);

        return to_route('learning.quizzes.show', $quiz);
    }

    public function show(Request $request, Quiz $quiz): Response
    {
        $this->authorizeManage($request->user(), $quiz);
        $quiz->load(['questions', 'attempts.student:id,first_name,last_name']);

        return Inertia::render('learning/quizzes/show', [
            'quiz' => [...$this->present($quiz), 'max_score' => (float) $quiz->questions->sum('marks')],
            'questions' => $quiz->questions->map(fn (QuizQuestion $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'type_label' => $question->type->label(),
                'prompt' => $question->prompt,
                'marks' => (float) $question->marks,
                'explanation' => $question->explanation,
                'options' => $question->options ?? [],
                'answer_key' => $question->answer_key ?? [],
            ])->values(),
            'attempts' => $quiz->attempts->sortByDesc('submitted_at')->map(fn (QuizAttempt $attempt): array => [
                'id' => $attempt->id,
                'student' => $attempt->student->full_name,
                'attempt_no' => $attempt->attempt_no,
                'status' => $attempt->status->value,
                'status_label' => $attempt->status->label(),
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'max_score' => (float) $attempt->max_score,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ])->values(),
            'questionTypes' => QuestionType::options(),
        ]);
    }

    public function edit(Request $request, Quiz $quiz): Response
    {
        $this->authorizeManage($request->user(), $quiz);

        return Inertia::render('learning/quizzes/form', [...$this->formOptions($request->user()), 'quiz' => $this->present($quiz)]);
    }

    public function update(QuizRequest $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($request->user(), $quiz);
        $this->authorizeAudience($request, $this->currentYear());

        if ($request->validated('status') === PublishStatus::Published->value && $quiz->questions()->doesntExist()) {
            throw ValidationException::withMessages(['status' => __('Add at least one question before publishing.')]);
        }

        $quiz->update($request->validated());
        $notified = $this->notifier->quizPublished($quiz->refresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => $notified > 0
            ? trans_choice('Published. :count student was notified.|Published. :count students were notified.', $notified, ['count' => $notified])
            : __('Quiz saved.')]);

        return to_route('learning.quizzes.show', $quiz);
    }

    public function destroy(Request $request, Quiz $quiz): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('quizzes.delete') && $this->scope->canManage($request->user(), 'quizzes', $quiz), 403);

        $quiz->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz deleted.')]);

        return to_route('learning.quizzes.index');
    }

    /* -------------------------------------------------------------- questions */

    public function storeQuestion(QuizQuestionRequest $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($request->user(), $quiz);
        $this->guardUnattempted($quiz);

        $quiz->questions()->create([...$request->payload(), 'school_id' => $quiz->school_id, 'position' => (int) $quiz->questions()->max('position') + 1]);

        return back();
    }

    public function updateQuestion(QuizQuestionRequest $request, QuizQuestion $question): RedirectResponse
    {
        $this->authorizeManage($request->user(), $question->quiz);
        $this->guardUnattempted($question->quiz);

        $question->update($request->payload());

        return back();
    }

    public function destroyQuestion(Request $request, QuizQuestion $question): RedirectResponse
    {
        $this->authorizeManage($request->user(), $question->quiz);
        $this->guardUnattempted($question->quiz);

        $question->delete();

        return back();
    }

    public function moveQuestion(Request $request, QuizQuestion $question): RedirectResponse
    {
        $this->authorizeManage($request->user(), $question->quiz);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $this->move($question, QuizQuestion::query()->where('quiz_id', $question->quiz_id), $direction);

        return back();
    }

    /* --------------------------------------------------------------- marking */

    public function attempt(Request $request, Quiz $quiz, QuizAttempt $attempt): Response
    {
        $this->authorizeManage($request->user(), $quiz);
        abort_unless($attempt->quiz_id === $quiz->id, 404);
        $attempt->load(['student:id,first_name,last_name', 'answers']);
        $answers = $attempt->answers->keyBy('quiz_question_id');

        return Inertia::render('learning/quizzes/attempt', [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title],
            'attempt' => [
                'id' => $attempt->id,
                'student' => $attempt->student->full_name,
                'status' => $attempt->status->value,
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'max_score' => (float) $attempt->max_score,
                'feedback' => $attempt->feedback,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ],
            'items' => $quiz->questions()->get()->map(function (QuizQuestion $question) use ($answers): array {
                /** @var QuizAnswer|null $answer */
                $answer = $answers->get($question->id);

                return [
                    'question' => [
                        'id' => $question->id,
                        'type' => $question->type->value,
                        'prompt' => $question->prompt,
                        'marks' => (float) $question->marks,
                        'options' => $question->options ?? [],
                        'answer_key' => $question->answer_key ?? [],
                    ],
                    'answer' => $answer === null ? null : [
                        'id' => $answer->id,
                        'value' => $answer->answer,
                        'is_correct' => $answer->is_correct,
                        'marks_awarded' => $answer->marks_awarded !== null ? (float) $answer->marks_awarded : null,
                        'feedback' => $answer->feedback,
                    ],
                ];
            })->values(),
        ]);
    }

    public function grade(Request $request, Quiz $quiz, QuizAttempt $attempt): RedirectResponse
    {
        $this->authorizeManage($request->user(), $quiz);
        abort_unless($attempt->quiz_id === $quiz->id, 404);

        $validated = $request->validate([
            'marks' => ['array'],
            'marks.*.marks' => ['nullable', 'numeric', 'min:0'],
            'marks.*.feedback' => ['nullable', 'string', 'max:2000'],
            'feedback' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->quizzes->grade($request->user(), $attempt, $validated['marks'] ?? [], $validated['feedback'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marks saved.')]);

        return to_route('learning.quizzes.show', $quiz);
    }

    /**
     * Questions cannot change once students have answered them, so marks stay fair.
     */
    private function guardUnattempted(Quiz $quiz): void
    {
        if ($quiz->attempts()->exists()) {
            throw ValidationException::withMessages(['question' => __('Students have already started this quiz, so its questions can no longer change.')]);
        }
    }

    /**
     * @param  Builder<Quiz>  $query
     */
    private function limitToTeacher(Builder $query, User $user, AcademicYear $year): void
    {
        $pairs = collect($this->scope->teachingOptions($user, 'quizzes', $year))
            ->flatMap(fn (array $class) => collect($class['subjects'])->map(fn (array $subject): array => [$class['id'], $subject['id']]));

        $query->where(function (Builder $query) use ($user, $pairs): void {
            $query->where('created_by', $user->id);

            foreach ($pairs as [$classId, $subjectId]) {
                $query->orWhere(fn (Builder $query) => $query->where('school_class_id', $classId)->where('subject_id', $subjectId));
            }
        });
    }

    private function authorizeManage(User $user, Quiz $quiz): void
    {
        abort_unless($this->scope->canManage($user, 'quizzes', $quiz), 403);
    }

    private function authorizeAudience(QuizRequest $request, AcademicYear $year): void
    {
        $allowed = $this->scope->canTeach(
            $request->user(),
            'quizzes',
            $request->integer('school_class_id'),
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->integer('subject_id'),
            $year,
        );

        if (! $allowed) {
            throw ValidationException::withMessages(['subject_id' => __('You can only set quizzes for classes and subjects you teach.')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $year = $this->currentYear();

        return [
            'options' => $this->scope->teachingOptions($user, 'quizzes', $year),
            'types' => QuizType::options(),
            'courses' => Course::query()->where('academic_year_id', $year->id)->orderBy('title')->get(['id', 'title', 'school_class_id', 'subject_id']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Quiz $quiz): array
    {
        $quiz->loadMissing('subject:id,name,color');

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'instructions' => $quiz->instructions,
            'type' => $quiz->type->value,
            'type_label' => $quiz->type->label(),
            'status' => $quiz->status->value,
            'school_class_id' => $quiz->school_class_id,
            'section_id' => $quiz->section_id,
            'subject_id' => $quiz->subject_id,
            'course_id' => $quiz->course_id,
            'subject' => $quiz->subject->name,
            'audience' => $this->audienceLabel($quiz),
            'opens_at' => $quiz->opens_at?->toIso8601String(),
            'closes_at' => $quiz->closes_at?->toIso8601String(),
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'max_attempts' => $quiz->max_attempts,
            'shuffle_questions' => $quiz->shuffle_questions,
            'show_results' => $quiz->show_results,
        ];
    }
}
