<?php

namespace App\Services\Academics\Exams;

use App\Enums\ExamResultsStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\Enrollment;
use App\Models\OnlineExam;
use App\Models\OnlineExamAttempt;
use App\Models\User;
use App\Notifications\LearningUpdate;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Results of an online exam: who sat it, each student's counted (best) attempt, ranks,
 * and the approve → publish workflow. Students see nothing of their marks until results
 * are published.
 */
class OnlineExamResults
{
    public function __construct(
        private OnlineExamService $exams,
        private AuditLogger $audit,
    ) {}

    /**
     * Lock the marks. Every attempt must be handed in and fully marked, and the exam over.
     */
    public function approve(OnlineExam $exam, User $user): void
    {
        if ($exam->results_status !== ExamResultsStatus::Pending) {
            throw ValidationException::withMessages(['results' => __('These results are already approved.')]);
        }

        if ($exam->ends_at->isFuture()) {
            throw ValidationException::withMessages(['results' => __('The exam is still running. Approve the results after it ends.')]);
        }

        $exam->attempts()->where('status', QuizAttemptStatus::InProgress)->get()
            ->each(fn (OnlineExamAttempt $attempt) => $this->exams->expireIfOverdue($attempt->setRelation('exam', $exam)));

        $toMark = $exam->attempts()->where('status', '!=', QuizAttemptStatus::Graded)->count();

        if ($toMark > 0) {
            throw ValidationException::withMessages(['results' => trans_choice(':count attempt still needs marking.|:count attempts still need marking.', $toMark, ['count' => $toMark])]);
        }

        DB::transaction(fn () => $exam->forceFill([
            'results_status' => ExamResultsStatus::Approved,
            'results_approved_by' => $user->id,
            'results_approved_at' => now(),
        ])->save());

        $this->audit->log('online_exam.results_approved', $exam);
    }

    /**
     * Unlock marks again (only before publishing).
     */
    public function reopen(OnlineExam $exam): void
    {
        if ($exam->results_status !== ExamResultsStatus::Approved) {
            throw ValidationException::withMessages(['results' => __('Only approved, unpublished results can be reopened for marking.')]);
        }

        $exam->forceFill(['results_status' => ExamResultsStatus::Pending, 'results_approved_by' => null, 'results_approved_at' => null])->save();
        $this->audit->log('online_exam.results_reopened', $exam);
    }

    /**
     * Release results to students (and parents). Returns how many accounts were notified.
     */
    public function publish(OnlineExam $exam, User $user): int
    {
        if ($exam->results_status !== ExamResultsStatus::Approved) {
            throw ValidationException::withMessages(['results' => __('Approve the results before publishing them.')]);
        }

        $exam->forceFill([
            'results_status' => ExamResultsStatus::Published,
            'results_published_by' => $user->id,
            'results_published_at' => now(),
        ])->save();

        $this->audit->log('online_exam.results_published', $exam);

        $studentIds = $exam->attempts()->distinct()->pluck('student_id');
        $recipients = User::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereHas('student', fn (Builder $students) => $students->whereIn('students.id', $studentIds))
                ->orWhereHas('guardian.students', fn (Builder $students) => $students->whereIn('students.id', $studentIds)))
            ->get();

        Notification::send($recipients, new LearningUpdate(
            'quiz',
            __('Results published: :title', ['title' => $exam->title]),
            $exam->subject()->value('name') ?? '',
            route('exams.my.show', $exam, false),
        ));

        return $recipients->count();
    }

    /**
     * Take published results back from students (back to approved).
     */
    public function withdraw(OnlineExam $exam): void
    {
        if ($exam->results_status !== ExamResultsStatus::Published) {
            throw ValidationException::withMessages(['results' => __('These results are not published.')]);
        }

        $exam->forceFill(['results_status' => ExamResultsStatus::Approved, 'results_published_by' => null, 'results_published_at' => null])->save();
        $this->audit->log('online_exam.results_withdrawn', $exam);
    }

    /**
     * Students who should sit the exam, with section, as id => details.
     *
     * @return Collection<int, array{student_id: int, name: string, admission_no: string, section: string, section_id: int}>
     */
    public function roster(OnlineExam $exam): Collection
    {
        $chosen = $exam->students()->pluck('students.id');

        return Enrollment::query()
            ->with(['student:id,first_name,last_name,admission_no', 'section:id,name,school_class_id'])
            ->where('academic_year_id', $exam->academic_year_id)
            ->whereHas('section', fn (Builder $query) => $query->where('school_class_id', $exam->school_class_id))
            ->when($exam->section_id !== null, fn (Builder $query) => $query->where('section_id', $exam->section_id))
            ->when($chosen->isNotEmpty(), fn (Builder $query) => $query->whereIn('student_id', $chosen))
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->student !== null)
            ->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->student_id => [
                'student_id' => $enrollment->student_id,
                'name' => $enrollment->student->full_name,
                'admission_no' => $enrollment->student->admission_no,
                'section' => $enrollment->section->name,
                'section_id' => $enrollment->section_id,
            ]]);
    }

    /**
     * Each student's counted attempt: the best fully-marked one, else the latest.
     *
     * @return Collection<int, OnlineExamAttempt> keyed by student id
     */
    public function countedAttempts(OnlineExam $exam): Collection
    {
        return $exam->attempts()->get()
            ->groupBy('student_id')
            ->map(function (Collection $attempts): OnlineExamAttempt {
                $graded = $attempts->filter(fn (OnlineExamAttempt $attempt): bool => $attempt->status === QuizAttemptStatus::Graded);

                return $graded->isNotEmpty()
                    ? $graded->sortByDesc(fn (OnlineExamAttempt $attempt): float => (float) $attempt->score)->first()
                    : $attempts->sortByDesc('attempt_no')->first();
            });
    }

    /**
     * Rank by score, ties sharing a rank (1, 2, 2, 4). Only fully-marked attempts rank.
     *
     * @param  Collection<int, OnlineExamAttempt>  $attempts
     * @return array<int, int> student id => rank
     */
    public function ranks(Collection $attempts): array
    {
        $ranks = [];
        $position = 0;
        $previous = null;
        $rank = 0;

        foreach ($attempts->filter(fn (OnlineExamAttempt $attempt): bool => $attempt->status === QuizAttemptStatus::Graded)->sortByDesc(fn (OnlineExamAttempt $attempt): float => (float) $attempt->score) as $attempt) {
            $position++;
            $score = (float) $attempt->score;

            if ($previous === null || $score < $previous) {
                $rank = $position;
                $previous = $score;
            }

            $ranks[$attempt->student_id] = $rank;
        }

        return $ranks;
    }
}
