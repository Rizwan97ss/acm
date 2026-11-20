<?php

namespace App\Services\Academics\Exams;

use App\Enums\ExamResultsStatus;
use App\Enums\PublishStatus;
use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Models\OnlineExam;
use App\Models\OnlineExamAnswer;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamQuestion;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\GradeScaleService;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of an online exam: publishing, sitting (start, autosave, hand in), automatic
 * hand-in when time runs out, and marking.
 *
 * The server is the only clock: each attempt's deadline is fixed when it starts (the
 * duration, cut short by the exam's end time) and answers arriving after it, beyond a
 * short grace for slow networks, are ignored. Attempts are locked while being saved or
 * handed in, so a double click or a replayed request cannot hand in twice.
 */
class OnlineExamService
{
    /**
     * Seconds of grace after the deadline, for slow networks.
     */
    public const int GRACE_SECONDS = 30;

    public function __construct(
        private AnswerMarker $marker,
        private GradeScaleService $gradeScales,
        private AuditLogger $audit,
    ) {}

    /* ------------------------------------------------------------- authoring */

    /**
     * Release a draft exam to students. The questions must add up to the total marks.
     */
    public function publish(OnlineExam $exam): void
    {
        $questions = $exam->questions()->get(['marks']);

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['status' => __('Add at least one question before publishing.')]);
        }

        $sum = round((float) $questions->sum(fn (OnlineExamQuestion $question): float => (float) $question->marks), 2);

        if (abs($sum - (float) $exam->total_marks) > 0.001) {
            throw ValidationException::withMessages(['status' => __('The questions add up to :sum marks but the exam is out of :total. Adjust the questions or the total marks.', ['sum' => $sum, 'total' => (float) $exam->total_marks])]);
        }

        if ($exam->ends_at->isPast()) {
            throw ValidationException::withMessages(['status' => __('The exam window has already ended. Change the end time first.')]);
        }

        $exam->forceFill(['status' => PublishStatus::Published])->save();
    }

    /**
     * Take an exam back to draft. Not possible once anyone has started it.
     */
    public function unpublish(OnlineExam $exam): void
    {
        if ($exam->isLocked()) {
            throw ValidationException::withMessages(['status' => __('Students have already started this exam, so it cannot go back to draft.')]);
        }

        $exam->forceFill(['status' => PublishStatus::Draft])->save();
    }

    /* --------------------------------------------------------------- sitting */

    /**
     * Start (or resume) the student's attempt. The caller has already checked that the
     * exam is one this student may sit.
     */
    public function start(Student $student, OnlineExam $exam, ?string $ip = null): OnlineExamAttempt
    {
        $open = $this->openAttempt($exam, $student);

        if ($open !== null) {
            return $this->expireIfOverdue($open);
        }

        if (! $exam->isOpen()) {
            throw ValidationException::withMessages(['exam' => __('This exam is not open right now.')]);
        }

        $used = OnlineExamAttempt::query()->where('online_exam_id', $exam->id)->where('student_id', $student->id)->count();

        if ($used >= $exam->max_attempts) {
            throw ValidationException::withMessages(['exam' => __('You have used all your attempts at this exam.')]);
        }

        $questions = $exam->questions()->get();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['exam' => __('This exam has no questions yet.')]);
        }

        $now = CarbonImmutable::now();
        $deadline = $now->addMinutes($exam->duration_minutes);

        try {
            $attempt = OnlineExamAttempt::query()->create([
                'school_id' => $exam->school_id,
                'online_exam_id' => $exam->id,
                'student_id' => $student->id,
                'attempt_no' => $used + 1,
                'status' => QuizAttemptStatus::InProgress,
                'started_at' => $now,
                'deadline_at' => $deadline->min($exam->ends_at),
                'layout' => $this->layout($exam, $questions),
                'max_score' => (float) $exam->total_marks,
                'ip_address' => $ip,
            ]);
        } catch (QueryException $exception) {
            // Two "start" requests at once: the unique (exam, student, attempt_no) key lets
            // only one through; the other resumes it.
            return $this->openAttempt($exam, $student) ?? throw $exception;
        }

        $this->audit->log('online_exam.started', $attempt, [], ['online_exam_id' => $exam->id, 'student_id' => $student->id, 'attempt_no' => $attempt->attempt_no]);

        return $attempt;
    }

    public function graceDeadline(OnlineExamAttempt $attempt): CarbonImmutable
    {
        return $attempt->deadline_at->addSeconds(self::GRACE_SECONDS);
    }

    /**
     * Autosave: keep the answers given so far. Returns the attempt as it now stands; if
     * time is up, the attempt is handed in with what was saved in time.
     *
     * @param  array<int|string, mixed>  $answers  question id => answer
     */
    public function save(OnlineExamAttempt $attempt, array $answers): OnlineExamAttempt
    {
        return DB::transaction(function () use ($attempt, $answers): OnlineExamAttempt {
            $locked = $this->lock($attempt);

            if (! $locked->isInProgress()) {
                return $locked;
            }

            if (now()->gt($this->graceDeadline($locked))) {
                return $this->finalise($locked, [], 'timeout');
            }

            $this->store($locked, $answers);
            $locked->forceFill(['last_saved_at' => now()])->save();

            return $locked;
        });
    }

    /**
     * Hand in. Answers sent after the deadline (plus grace) are ignored; whatever was
     * autosaved in time still counts.
     *
     * @param  array<int|string, mixed>  $answers
     */
    public function submit(OnlineExamAttempt $attempt, array $answers, string $by = 'student'): OnlineExamAttempt
    {
        $attempt = DB::transaction(function () use ($attempt, $answers, $by): OnlineExamAttempt {
            $locked = $this->lock($attempt);

            if (! $locked->isInProgress()) {
                throw ValidationException::withMessages(['exam' => __('This attempt has already been handed in.')]);
            }

            $late = now()->gt($this->graceDeadline($locked));

            return $this->finalise($locked, $late ? [] : $answers, $late ? 'timeout' : $by);
        });

        $this->audit->log('online_exam.submitted', $attempt, [], ['submitted_by' => $attempt->submitted_by, 'score' => $attempt->score]);

        return $attempt;
    }

    /**
     * Hand in an attempt whose time has run out.
     */
    public function expireIfOverdue(OnlineExamAttempt $attempt): OnlineExamAttempt
    {
        if (! $attempt->isInProgress() || now()->lte($this->graceDeadline($attempt))) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt): OnlineExamAttempt {
            $locked = $this->lock($attempt);

            return $locked->isInProgress() ? $this->finalise($locked, [], 'timeout') : $locked;
        });
    }

    /**
     * Hand in every overdue attempt of the current school (scheduled), so results never
     * wait on a student coming back. Returns how many were closed.
     */
    public function closeOverdue(): int
    {
        $closed = 0;

        OnlineExamAttempt::query()
            ->where('status', QuizAttemptStatus::InProgress)
            ->where('deadline_at', '<', now()->subSeconds(self::GRACE_SECONDS))
            ->orderBy('id')
            ->each(function (OnlineExamAttempt $attempt) use (&$closed): void {
                $this->expireIfOverdue($attempt);
                $closed++;
            });

        return $closed;
    }

    /* --------------------------------------------------------------- marking */

    /**
     * The teacher marks written answers and may adjust any mark, until results are approved.
     *
     * @param  array<int|string, array{marks?: float|int|string|null, feedback?: string|null}>  $marks  answer id => mark
     */
    public function grade(User $teacher, OnlineExamAttempt $attempt, array $marks, ?string $feedback): OnlineExamAttempt
    {
        $exam = $attempt->exam;

        if ($exam->results_status !== ExamResultsStatus::Pending) {
            throw ValidationException::withMessages(['attempt' => __('Results are approved, so marks are locked. Reopen marking first.')]);
        }

        if ($attempt->isInProgress()) {
            throw ValidationException::withMessages(['attempt' => __('The student has not handed this in yet.')]);
        }

        DB::transaction(function () use ($attempt, $exam, $marks, $feedback, $teacher): void {
            $locked = $this->lock($attempt);

            foreach ($locked->answers()->with('question')->get() as $answer) {
                $entry = $marks[$answer->id] ?? $marks[(string) $answer->id] ?? null;

                if (! is_array($entry)) {
                    continue;
                }

                [$floor, $ceiling] = $this->marker->range($answer->question, $exam->negative_marking);
                $given = $entry['marks'] ?? null;
                $value = $given === null || $given === '' ? null : max($floor, min($ceiling, (float) $given));

                $answer->forceFill([
                    'marks_awarded' => $value,
                    'is_correct' => $value === null ? null : $value >= $ceiling,
                    'feedback' => array_key_exists('feedback', $entry) ? $entry['feedback'] : $answer->feedback,
                ])->save();
            }

            $locked->forceFill(['feedback' => $feedback, 'graded_by' => $teacher->id])->save();
            $this->settle($locked);
        });

        $this->audit->log('online_exam.graded', $attempt, [], ['score' => $attempt->refresh()->score]);

        return $attempt;
    }

    /**
     * Recalculate the score and, once nothing is left to mark, the percentage, grade and
     * pass/fail.
     */
    public function settle(OnlineExamAttempt $attempt): void
    {
        $exam = $attempt->exam;
        $answers = $attempt->answers()->get();
        $pending = $answers->contains(fn (OnlineExamAnswer $answer): bool => $answer->marks_awarded === null && $answer->answer !== null);
        $score = max(0.0, round((float) $answers->sum(fn (OnlineExamAnswer $answer): float => (float) $answer->marks_awarded), 2));
        $max = (float) $attempt->max_score;
        $percentage = $max > 0 ? round($score / $max * 100, 2) : 0.0;

        $attempt->forceFill([
            'score' => $score,
            'status' => $pending ? QuizAttemptStatus::Submitted : QuizAttemptStatus::Graded,
            'graded_at' => $pending ? null : now(),
            'percentage' => $pending ? null : $percentage,
            'grade' => $pending ? null : $this->gradeFor($exam->school_id, $percentage),
            'passed' => $pending ? null : $score >= (float) $exam->passing_marks,
        ])->save();
    }

    /* --------------------------------------------------------------- helpers */

    private function openAttempt(OnlineExam $exam, Student $student): ?OnlineExamAttempt
    {
        return OnlineExamAttempt::query()
            ->where('online_exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('status', QuizAttemptStatus::InProgress)
            ->first();
    }

    private function lock(OnlineExamAttempt $attempt): OnlineExamAttempt
    {
        $locked = OnlineExamAttempt::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($attempt->id);
        $locked->setRelation('exam', $attempt->relationLoaded('exam') ? $attempt->exam : $locked->exam()->withoutGlobalScopes()->firstOrFail());

        return $locked;
    }

    /**
     * Question order (and option order) for this attempt, shuffled if the exam says so.
     *
     * @param  Collection<int, OnlineExamQuestion>  $questions
     * @return list<array{question: int, options: list<string>}>
     */
    private function layout(OnlineExam $exam, Collection $questions): array
    {
        $ordered = $exam->shuffle_questions ? $questions->shuffle() : $questions;

        return array_values($ordered->map(function (OnlineExamQuestion $question) use ($exam): array {
            $options = array_column($question->options ?? [], 'id');

            if ($exam->shuffle_options && $question->type->hasOptions()) {
                shuffle($options);
            }

            return ['question' => $question->id, 'options' => $options];
        })->all());
    }

    /**
     * Save answers for questions that are on this attempt's paper (others are ignored).
     *
     * @param  array<int|string, mixed>  $answers
     */
    private function store(OnlineExamAttempt $attempt, array $answers): void
    {
        $onPaper = array_column($attempt->layout, 'question');
        $questions = OnlineExamQuestion::query()->withoutGlobalScopes()->whereIn('id', $onPaper)->get()->keyBy('id');

        foreach ($answers as $questionId => $given) {
            $question = $questions->get((int) $questionId);

            if ($question === null) {
                continue;
            }

            OnlineExamAnswer::query()->withoutGlobalScopes()->updateOrCreate(
                ['online_exam_attempt_id' => $attempt->id, 'online_exam_question_id' => $question->id],
                ['school_id' => $attempt->school_id, 'answer' => $this->marker->normalise($question, $given)],
            );
        }
    }

    /**
     * Merge the final answers into what was saved, mark everything and close the attempt.
     *
     * @param  array<int|string, mixed>  $answers
     */
    private function finalise(OnlineExamAttempt $attempt, array $answers, string $by): OnlineExamAttempt
    {
        $this->store($attempt, $answers);

        $exam = $attempt->exam;
        $questions = OnlineExamQuestion::query()->withoutGlobalScopes()->whereIn('id', array_column($attempt->layout, 'question'))->get();
        $saved = OnlineExamAnswer::query()->withoutGlobalScopes()->where('online_exam_attempt_id', $attempt->id)->get()->keyBy('online_exam_question_id');

        foreach ($questions as $question) {
            $given = $saved->get($question->id)?->answer;
            $given = is_array($given) || is_string($given) ? $given : null;
            [$correct, $awarded] = $this->marker->mark($question, $given, $exam->negative_marking);

            OnlineExamAnswer::query()->withoutGlobalScopes()->updateOrCreate(
                ['online_exam_attempt_id' => $attempt->id, 'online_exam_question_id' => $question->id],
                ['school_id' => $attempt->school_id, 'answer' => $given, 'is_correct' => $correct, 'marks_awarded' => $awarded],
            );
        }

        $attempt->forceFill(['submitted_at' => now(), 'submitted_by' => $by])->save();
        $attempt->setRelation('exam', $exam);
        $this->settle($attempt);

        return $attempt;
    }

    private function gradeFor(int $schoolId, float $percentage): ?string
    {
        $scale = $this->gradeScales->defaultFor($schoolId)->loadMissing('bands');

        return $scale->bandFor($percentage)?->grade;
    }

    /**
     * Is this a question type the teacher must mark by hand when answered?
     */
    public static function needsHandMarking(OnlineExamQuestion $question): bool
    {
        return $question->type === QuestionType::Essay || ($question->type === QuestionType::ShortAnswer && ($question->answer_key ?? []) === []);
    }
}
