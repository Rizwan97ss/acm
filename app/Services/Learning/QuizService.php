<?php

namespace App\Services\Learning;

use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Starting, submitting and marking quiz attempts.
 *
 * Objective questions (single/multiple choice, true/false) and short answers with an
 * answer key are marked on submission. Essays, and short answers without a key, wait for
 * the teacher; the attempt is "graded" once nothing is left to mark.
 */
class QuizService
{
    /**
     * Seconds of grace after the time limit, for slow networks.
     */
    public const int SUBMIT_GRACE_SECONDS = 90;

    public function __construct(private LearningNotifier $notifier) {}

    public function start(Student $student, Quiz $quiz): QuizAttempt
    {
        $open = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->where('status', QuizAttemptStatus::InProgress)->first();

        if ($open !== null) {
            return $this->expireIfOverdue($open);
        }

        if (! $quiz->isOpen()) {
            throw ValidationException::withMessages(['quiz' => __('This quiz is not open.')]);
        }

        $used = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->count();

        if ($used >= $quiz->max_attempts) {
            throw ValidationException::withMessages(['quiz' => __('You have used all your attempts.')]);
        }

        if ($quiz->questions()->doesntExist()) {
            throw ValidationException::withMessages(['quiz' => __('This quiz has no questions yet.')]);
        }

        return QuizAttempt::query()->create([
            'school_id' => $quiz->school_id,
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'attempt_no' => $used + 1,
            'started_at' => now(),
            'max_score' => (float) $quiz->questions()->sum('marks'),
            'status' => QuizAttemptStatus::InProgress,
        ]);
    }

    /**
     * When the attempt must be handed in: the time limit or the closing time, whichever is first.
     */
    public function deadline(QuizAttempt $attempt): ?CarbonImmutable
    {
        $quiz = $attempt->quiz;
        $limit = $quiz->time_limit_minutes !== null ? $attempt->started_at->addMinutes($quiz->time_limit_minutes) : null;

        return collect([$limit, $quiz->closes_at])->filter()->sort()->first();
    }

    /**
     * @param  array<int|string, mixed>  $answers  question id => answer (option id, list of ids, "true"/"false", or text)
     */
    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        if ($attempt->status !== QuizAttemptStatus::InProgress) {
            throw ValidationException::withMessages(['quiz' => __('This attempt has already been handed in.')]);
        }

        $deadline = $this->deadline($attempt);

        if ($deadline !== null && now()->gt($deadline->addSeconds(self::SUBMIT_GRACE_SECONDS))) {
            $answers = [];
        }

        return DB::transaction(function () use ($attempt, $answers): QuizAttempt {
            $questions = $attempt->quiz->questions()->get();
            $needsMarking = false;
            $score = 0.0;

            foreach ($questions as $question) {
                $given = $this->normalise($question, $answers[$question->id] ?? $answers[(string) $question->id] ?? null);
                [$correct, $awarded] = $this->autoMark($question, $given);

                $needsMarking = $needsMarking || ($correct === null && $given !== null);
                $score += $awarded ?? 0;

                QuizAnswer::query()->updateOrCreate(
                    ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
                    ['school_id' => $attempt->school_id, 'answer' => $given, 'is_correct' => $correct, 'marks_awarded' => $given === null ? 0 : $awarded],
                );
            }

            $attempt->forceFill([
                'submitted_at' => now(),
                'score' => $score,
                'status' => $needsMarking ? QuizAttemptStatus::Submitted : QuizAttemptStatus::Graded,
                'graded_at' => $needsMarking ? null : now(),
            ])->save();

            return $attempt;
        });
    }

    /**
     * The teacher marks descriptive answers and may adjust any mark.
     *
     * @param  array<int|string, array{marks: float|int|string|null, feedback?: string|null}>  $marks  answer id => mark
     */
    public function grade(User $teacher, QuizAttempt $attempt, array $marks, ?string $feedback): QuizAttempt
    {
        if ($attempt->status === QuizAttemptStatus::InProgress) {
            throw ValidationException::withMessages(['attempt' => __('The student has not handed this in yet.')]);
        }

        DB::transaction(function () use ($attempt, $marks, $feedback, $teacher): void {
            foreach ($attempt->answers()->with('question')->get() as $answer) {
                $entry = $marks[$answer->id] ?? $marks[(string) $answer->id] ?? null;

                if ($entry === null) {
                    continue;
                }

                $max = (float) $answer->question->marks;
                $value = $entry['marks'] === null || $entry['marks'] === '' ? null : min($max, max(0, (float) $entry['marks']));

                $answer->forceFill([
                    'marks_awarded' => $value,
                    'is_correct' => $value === null ? null : $value >= $max,
                    'feedback' => $entry['feedback'] ?? $answer->feedback,
                ])->save();
            }

            $answers = $attempt->answers()->get();
            $pending = $answers->contains(fn (QuizAnswer $answer): bool => $answer->marks_awarded === null && $answer->answer !== null);

            $attempt->forceFill([
                'score' => (float) $answers->sum(fn (QuizAnswer $answer): float => (float) $answer->marks_awarded),
                'feedback' => $feedback,
                'status' => $pending ? QuizAttemptStatus::Submitted : QuizAttemptStatus::Graded,
                'graded_by' => $teacher->id,
                'graded_at' => $pending ? null : now(),
            ])->save();
        });

        $attempt->refresh();

        if ($attempt->status === QuizAttemptStatus::Graded) {
            $this->notifier->quizGraded($attempt);
        }

        return $attempt;
    }

    /**
     * Hand in an attempt whose time ran out with no answers saved.
     */
    public function expireIfOverdue(QuizAttempt $attempt): QuizAttempt
    {
        $deadline = $this->deadline($attempt);

        if ($attempt->status === QuizAttemptStatus::InProgress && $deadline !== null && now()->gt($deadline->addSeconds(self::SUBMIT_GRACE_SECONDS))) {
            return $this->submit($attempt, []);
        }

        return $attempt;
    }

    /**
     * @return list<string>|string|null
     */
    private function normalise(QuizQuestion $question, mixed $given): array|string|null
    {
        if ($given === null || $given === '' || $given === []) {
            return null;
        }

        return match ($question->type) {
            QuestionType::MultipleChoice => array_values(array_unique(array_map('strval', (array) $given))),
            QuestionType::TrueFalse => in_array($given, ['true', true, '1', 1], true) ? 'true' : 'false',
            default => Str::limit(trim((string) (is_array($given) ? reset($given) : $given)), 10000, ''),
        };
    }

    /**
     * @param  list<string>|string|null  $given
     * @return array{0: bool|null, 1: float|null} [correct?, marks]; null when the teacher must mark
     */
    private function autoMark(QuizQuestion $question, array|string|null $given): array
    {
        $marks = (float) $question->marks;
        $key = array_map('strval', $question->answer_key ?? []);

        if ($given === null) {
            return [false, 0.0];
        }

        $correct = match ($question->type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse => is_string($given) && in_array($given, $key, true),
            QuestionType::MultipleChoice => is_array($given) && $key !== [] && empty(array_diff($given, $key)) && empty(array_diff($key, $given)),
            QuestionType::ShortAnswer => $key === [] ? null : (is_string($given) && in_array(Str::lower(trim($given)), array_map(fn (string $accepted): string => Str::lower(trim($accepted)), $key), true)),
            QuestionType::Essay => null,
        };

        return [$correct, $correct === null ? null : ($correct ? $marks : 0.0)];
    }
}
