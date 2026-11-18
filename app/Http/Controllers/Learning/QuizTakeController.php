<?php

namespace App\Http\Controllers\Learning;

use App\Enums\QuizAttemptStatus;
use App\Models\AcademicYear;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Student;
use App\Services\Learning\LearningScope;
use App\Services\Learning\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Students take quizzes and see their results. Answer keys are never sent to the page
 * until the attempt is marked and the teacher allows results to be shown.
 */
class QuizTakeController extends LearningController
{
    public function __construct(LearningScope $scope, private QuizService $quizzes)
    {
        parent::__construct($scope);
    }

    public function show(Request $request, Quiz $quiz): Response
    {
        $student = $this->student($request, $quiz);
        $quiz->load(['questions', 'subject:id,name,color']);

        $attempts = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->orderBy('attempt_no')->get()
            ->map(fn (QuizAttempt $attempt): QuizAttempt => $this->quizzes->expireIfOverdue($attempt));
        $current = $attempts->firstWhere('status', QuizAttemptStatus::InProgress);
        $reviewed = $attempts->filter(fn (QuizAttempt $attempt): bool => $attempt->status !== QuizAttemptStatus::InProgress)->last();
        $questions = $quiz->shuffle_questions && $current !== null
            ? $quiz->questions->sortBy(fn (QuizQuestion $question): int => crc32($current->id.':'.$question->id))->values()
            : $quiz->questions;

        $reviewAnswers = $reviewed !== null && $quiz->show_results ? QuizAnswer::query()->where('quiz_attempt_id', $reviewed->id)->get()->keyBy('quiz_question_id') : collect();
        $showKey = $reviewed !== null && $quiz->show_results && $reviewed->status === QuizAttemptStatus::Graded;

        return Inertia::render('learn/quiz', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'type' => $quiz->type->label(),
                'instructions' => $quiz->instructions,
                'subject' => ['name' => $quiz->subject->name, 'color' => $quiz->subject->color],
                'opens_at' => $quiz->opens_at?->toIso8601String(),
                'closes_at' => $quiz->closes_at?->toIso8601String(),
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'max_attempts' => $quiz->max_attempts,
                'is_open' => $quiz->isOpen(),
                'questions_count' => $quiz->questions->count(),
                'max_score' => (float) $quiz->questions->sum('marks'),
                'show_results' => $quiz->show_results,
            ],
            'attemptsUsed' => $attempts->count(),
            'current' => $current === null ? null : [
                'id' => $current->id,
                'deadline' => $this->quizzes->deadline($current)?->toIso8601String(),
            ],
            'questions' => $current === null && ! $showKey && $reviewAnswers->isEmpty() ? [] : $questions->map(fn (QuizQuestion $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'marks' => (float) $question->marks,
                'options' => $question->options ?? [],
                'answer_key' => $showKey ? ($question->answer_key ?? []) : null,
                'explanation' => $showKey ? $question->explanation : null,
                'review' => ($answer = $reviewAnswers->get($question->id)) === null || $current !== null ? null : [
                    'value' => $answer->answer,
                    'is_correct' => $answer->is_correct,
                    'marks_awarded' => $answer->marks_awarded !== null ? (float) $answer->marks_awarded : null,
                    'feedback' => $answer->feedback,
                ],
            ])->values(),
            'result' => $reviewed === null ? null : [
                'status' => $reviewed->status->value,
                'status_label' => $reviewed->status->label(),
                'score' => $quiz->show_results && $reviewed->score !== null ? (float) $reviewed->score : null,
                'max_score' => (float) $reviewed->max_score,
                'feedback' => $reviewed->feedback,
                'submitted_at' => $reviewed->submitted_at?->toIso8601String(),
            ],
        ]);
    }

    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->quizzes->start($this->student($request, $quiz), $quiz);

        return to_route('learn.quizzes.show', $quiz);
    }

    public function submit(Request $request, Quiz $quiz, QuizAttempt $attempt): RedirectResponse
    {
        $student = $this->student($request, $quiz);
        abort_unless($attempt->quiz_id === $quiz->id && $attempt->student_id === $student->id, 404);

        $validated = $request->validate([
            'answers' => ['nullable', 'array', 'max:500'],
            'answers.*' => ['nullable'],
        ]);

        $attempt = $this->quizzes->submit($attempt, $validated['answers'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => $attempt->status === QuizAttemptStatus::Graded
            ? __('Handed in and marked.')
            : __('Handed in. Your teacher will mark the written answers.')]);

        return to_route('learn.quizzes.show', $quiz);
    }

    private function student(Request $request, Quiz $quiz): Student
    {
        Gate::authorize('quizzes.view');
        $student = $request->user()->student;
        $year = AcademicYear::query()->current()->first();

        abort_if($student === null || $year === null || $request->user()->employee()->exists(), 403);
        abort_unless($this->scope->quizzesFor($student, $year)->whereKey($quiz->id)->exists(), 404);

        return $student;
    }
}
