<?php

namespace App\Services\Academics\Exams;

use App\Enums\ExamResultsStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\OnlineExam;
use App\Models\OnlineExamAnswer;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamQuestion;
use App\Models\User;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Performance reports for online exams.
 *
 * Exam reports: averages, pass rate, score distribution, grades, sections, per-question
 * results and standings. The Python analytics service adds item analysis (difficulty,
 * discrimination, point-biserial) and distribution statistics when it is configured;
 * everything else is computed here so reports work without it.
 *
 * Overview reports (exam-, class-, subject- and student-wise) count only exams whose
 * results are approved or published, so half-marked exams never skew them.
 */
class OnlineExamReports
{
    public const int BINS = 10;

    public function __construct(
        private OnlineExamResults $results,
        private OnlineExamScope $scope,
        private AnalyticsClient $analytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function exam(OnlineExam $exam, bool $withAnalysis = true): array
    {
        $roster = $this->results->roster($exam);
        $counted = $this->results->countedAttempts($exam);
        $ranks = $this->results->ranks($counted);
        $graded = $counted->filter(fn (OnlineExamAttempt $attempt): bool => $attempt->status === QuizAttemptStatus::Graded);
        $percentages = $graded->map(fn (OnlineExamAttempt $attempt): float => (float) $attempt->percentage)->values();
        $questions = $exam->questions()->get();
        $answers = OnlineExamAnswer::query()->whereIn('online_exam_attempt_id', $graded->pluck('id'))->get();

        return [
            'summary' => [
                'eligible' => $roster->count(),
                'sat' => $counted->filter(fn (OnlineExamAttempt $attempt): bool => ! $attempt->isInProgress())->count(),
                'in_progress' => $counted->filter(fn (OnlineExamAttempt $attempt): bool => $attempt->isInProgress())->count(),
                'absent' => $roster->keys()->diff($counted->keys())->count(),
                'to_mark' => $counted->filter(fn (OnlineExamAttempt $attempt): bool => $attempt->status === QuizAttemptStatus::Submitted)->count(),
                'graded' => $graded->count(),
                'average' => $this->round($percentages->avg()),
                'median' => $this->round($percentages->median()),
                'highest' => $this->round($percentages->max()),
                'lowest' => $this->round($percentages->min()),
                'passed' => $graded->where('passed', true)->count(),
                'pass_rate' => $graded->isEmpty() ? null : round($graded->where('passed', true)->count() / $graded->count() * 100, 1),
            ],
            'distribution' => $this->distribution($percentages),
            'grades' => $graded->groupBy(fn (OnlineExamAttempt $attempt): string => $attempt->grade ?? '—')
                ->map(fn (Collection $group, string $grade): array => ['grade' => $grade, 'students' => $group->count()])
                ->sortBy('grade')->values()->all(),
            'sections' => $this->sections($graded, $roster),
            'questions' => $this->questionStats($questions, $answers, $graded->count()),
            'standings' => $this->standings($roster, $counted, $ranks),
            'analysis' => $withAnalysis ? $this->analysis($exam, $graded, $questions, $answers, $roster) : null,
        ];
    }

    /**
     * Rows for the results download.
     *
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public function exportRows(OnlineExam $exam): array
    {
        $headings = array_map(fn (string $heading): string => trans($heading), ['Rank', 'Admission no.', 'Student', 'Section', 'Status', 'Score', 'Out of', 'Percentage', 'Grade', 'Result', 'Handed in']);
        $rows = array_values(array_map(fn (array $row): array => [
            $row['rank'],
            $row['admission_no'],
            $row['name'],
            $row['section'],
            $row['status_label'],
            $row['score'],
            (float) $exam->total_marks,
            $row['percentage'],
            $row['grade'],
            $row['passed'] === null ? '' : ($row['passed'] ? __('Pass') : __('Fail')),
            $row['submitted_at'],
        ], $this->exam($exam, false)['standings']));

        return [$headings, $rows];
    }

    /**
     * Exam-, class-, subject- and (for one class) student-wise performance.
     *
     * @param  array{class_id?: int|null, subject_id?: int|null, section_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function overview(User $user, AcademicYear $year, array $filters): array
    {
        $exams = $this->scope->forStaff(OnlineExam::query(), $user, $year)
            ->with(['schoolClass:id,name,level', 'subject:id,name', 'section:id,name'])
            ->where('academic_year_id', $year->id)
            ->whereIn('results_status', [ExamResultsStatus::Approved, ExamResultsStatus::Published])
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('school_class_id', $classId))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $sectionId) => $query->where(fn (Builder $query) => $query->whereNull('section_id')->orWhere('section_id', $sectionId)))
            ->orderByDesc('starts_at')
            ->get();

        $rows = collect();

        foreach ($exams as $exam) {
            foreach ($this->results->countedAttempts($exam) as $attempt) {
                if ($attempt->status === QuizAttemptStatus::Graded) {
                    $rows->push(['exam' => $exam, 'attempt' => $attempt]);
                }
            }
        }

        if (($filters['section_id'] ?? null) !== null) {
            $inSection = $this->studentIdsInSection((int) $filters['section_id'], $year);
            $rows = $rows->filter(fn (array $row): bool => in_array($row['attempt']->student_id, $inSection, true));
        }

        return [
            'summary' => [
                'exams' => $exams->count(),
                'attempts' => $rows->count(),
                'average' => $this->round($rows->avg(fn (array $row): float => (float) $row['attempt']->percentage)),
                'pass_rate' => $rows->isEmpty() ? null : round($rows->filter(fn (array $row): bool => $row['attempt']->passed === true)->count() / $rows->count() * 100, 1),
            ],
            'exams' => $exams->map(function (OnlineExam $exam) use ($rows): array {
                $mine = $rows->filter(fn (array $row): bool => $row['exam']->id === $exam->id);

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'class' => $exam->schoolClass->name.($exam->section !== null ? ' – '.$exam->section->name : ''),
                    'subject' => $exam->subject->name,
                    'date' => $exam->starts_at->toIso8601String(),
                    ...$this->aggregate($mine),
                ];
            })->values()->all(),
            'classes' => $rows->groupBy(fn (array $row): int => $row['exam']->school_class_id)
                ->map(fn (Collection $group): array => ['name' => $group->first()['exam']->schoolClass->name, 'level' => (int) $group->first()['exam']->schoolClass->level, ...$this->aggregate($group)])
                ->sortBy('level')->values()->all(),
            'subjects' => $rows->groupBy(fn (array $row): int => $row['exam']->subject_id)
                ->map(fn (Collection $group): array => ['name' => $group->first()['exam']->subject->name, ...$this->aggregate($group)])
                ->sortBy('name')->values()->all(),
            'students' => ($filters['class_id'] ?? null) !== null ? $this->studentWise($rows) : [],
        ];
    }

    /**
     * A student's own published results (for the student and their parents).
     *
     * @return list<array<string, mixed>>
     */
    public function studentHistory(int $studentId, AcademicYear $year): array
    {
        return array_values(OnlineExamAttempt::query()
            ->with('exam.subject:id,name')
            ->where('student_id', $studentId)
            ->where('status', QuizAttemptStatus::Graded)
            ->whereHas('exam', fn (Builder $query) => $query->where('academic_year_id', $year->id)->where('results_status', ExamResultsStatus::Published))
            ->get()
            ->groupBy('online_exam_id')
            ->map(fn (Collection $attempts): OnlineExamAttempt => $attempts->sortByDesc(fn (OnlineExamAttempt $attempt): float => (float) $attempt->score)->first())
            ->sortBy(fn (OnlineExamAttempt $attempt): int => $attempt->exam->starts_at->getTimestamp())
            ->map(fn (OnlineExamAttempt $attempt): array => [
                'exam_id' => $attempt->online_exam_id,
                'title' => $attempt->exam->title,
                'subject' => $attempt->exam->subject->name,
                'date' => $attempt->exam->starts_at->toIso8601String(),
                'percentage' => (float) $attempt->percentage,
                'grade' => $attempt->grade,
                'passed' => $attempt->passed,
            ])
            ->all());
    }

    /* --------------------------------------------------------------- helpers */

    /**
     * @param  Collection<int, float>  $percentages
     * @return list<array{label: string, students: int}>
     */
    private function distribution(Collection $percentages): array
    {
        $bins = array_fill(0, self::BINS, 0);

        foreach ($percentages as $percentage) {
            $bins[min(self::BINS - 1, (int) floor($percentage / (100 / self::BINS)))]++;
        }

        return array_map(fn (int $index): array => [
            'label' => ($index * 10).'–'.($index === self::BINS - 1 ? 100 : $index * 10 + 9),
            'students' => $bins[$index],
        ], array_keys($bins));
    }

    /**
     * @param  Collection<int, OnlineExamAttempt>  $graded
     * @param  Collection<int, array{student_id: int, name: string, admission_no: string, section: string, section_id: int}>  $roster
     * @return list<array<string, mixed>>
     */
    private function sections(Collection $graded, Collection $roster): array
    {
        return array_values($graded->groupBy(fn (OnlineExamAttempt $attempt): string => $roster->get($attempt->student_id)['section'] ?? __('Not enrolled'))
            ->map(fn (Collection $group, string $section): array => [
                'section' => $section,
                'students' => $group->count(),
                'average' => $this->round($group->avg(fn (OnlineExamAttempt $attempt): float => (float) $attempt->percentage)),
                'pass_rate' => round($group->where('passed', true)->count() / max(1, $group->count()) * 100, 1),
            ])
            ->sortBy('section')->values()->all());
    }

    /**
     * @param  Collection<int, OnlineExamQuestion>  $questions
     * @param  Collection<int, OnlineExamAnswer>  $answers
     * @return list<array<string, mixed>>
     */
    private function questionStats(Collection $questions, Collection $answers, int $graded): array
    {
        $byQuestion = $answers->groupBy('online_exam_question_id');

        return array_values($questions->values()->map(function (OnlineExamQuestion $question, int $index) use ($byQuestion, $graded): array {
            /** @var Collection<int, OnlineExamAnswer> $given */
            $given = $byQuestion->get($question->id, collect());
            $answered = $given->filter(fn (OnlineExamAnswer $answer): bool => $answer->answer !== null);
            $marks = (float) $question->marks;
            $average = $graded > 0 ? (float) $given->sum(fn (OnlineExamAnswer $answer): float => (float) $answer->marks_awarded) / $graded : null;

            return [
                'id' => $question->id,
                'number' => $index + 1,
                'prompt' => Str::limit($question->prompt, 90),
                'type' => $question->type->label(),
                'marks' => $marks,
                'answered' => $answered->count(),
                'correct' => $given->where('is_correct', true)->count(),
                'correct_rate' => $graded > 0 ? round($given->where('is_correct', true)->count() / $graded * 100, 1) : null,
                'average_marks' => $average === null ? null : round($average, 2),
                'average_rate' => $average === null || $marks <= 0 ? null : round($average / $marks * 100, 1),
            ];
        })->all());
    }

    /**
     * @param  Collection<int, array{student_id: int, name: string, admission_no: string, section: string, section_id: int}>  $roster
     * @param  Collection<int, OnlineExamAttempt>  $counted
     * @param  array<int, int>  $ranks
     * @return list<array<string, mixed>>
     */
    private function standings(Collection $roster, Collection $counted, array $ranks): array
    {
        $rows = $roster->map(function (array $student) use ($counted, $ranks): array {
            /** @var OnlineExamAttempt|null $attempt */
            $attempt = $counted->get($student['student_id']);

            return [
                ...$student,
                'attempt_id' => $attempt?->id,
                'attempt_no' => $attempt?->attempt_no,
                'status' => $attempt === null ? 'absent' : $attempt->status->value,
                'status_label' => $attempt === null ? __('Did not sit') : $attempt->status->label(),
                'score' => $attempt?->score !== null ? (float) $attempt->score : null,
                'percentage' => $attempt?->percentage !== null ? (float) $attempt->percentage : null,
                'grade' => $attempt?->grade,
                'passed' => $attempt?->passed,
                'rank' => $ranks[$student['student_id']] ?? null,
                'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
                'submitted_by' => $attempt?->submitted_by,
            ];
        });

        // Students who sat but have since left the class still appear.
        foreach ($counted as $studentId => $attempt) {
            if (! $roster->has($studentId)) {
                $attempt->loadMissing('student:id,first_name,last_name,admission_no');
                $rows->put($studentId, [
                    'student_id' => $studentId,
                    'name' => $attempt->student->full_name,
                    'admission_no' => $attempt->student->admission_no,
                    'section' => __('Not enrolled'),
                    'section_id' => 0,
                    'attempt_id' => $attempt->id,
                    'attempt_no' => $attempt->attempt_no,
                    'status' => $attempt->status->value,
                    'status_label' => $attempt->status->label(),
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'percentage' => $attempt->percentage !== null ? (float) $attempt->percentage : null,
                    'grade' => $attempt->grade,
                    'passed' => $attempt->passed,
                    'rank' => $ranks[$studentId] ?? null,
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'submitted_by' => $attempt->submitted_by,
                ]);
            }
        }

        return array_values($rows->sortBy([
            fn (array $a, array $b): int => ($a['rank'] ?? PHP_INT_MAX) <=> ($b['rank'] ?? PHP_INT_MAX),
            fn (array $a, array $b): int => strcmp($a['name'], $b['name']),
        ])->values()->all());
    }

    /**
     * Item analysis from the Python service; null when it is not configured or offline.
     *
     * @param  Collection<int, OnlineExamAttempt>  $graded
     * @param  Collection<int, OnlineExamQuestion>  $questions
     * @param  Collection<int, OnlineExamAnswer>  $answers
     * @param  Collection<int, array{student_id: int, name: string, admission_no: string, section: string, section_id: int}>  $roster
     * @return array<string, mixed>|null
     */
    private function analysis(OnlineExam $exam, Collection $graded, Collection $questions, Collection $answers, Collection $roster): ?array
    {
        if ($graded->count() < 2 || ! $this->analytics->isConfigured()) {
            return null;
        }

        $attemptStudent = $graded->mapWithKeys(fn (OnlineExamAttempt $attempt): array => [$attempt->id => $attempt->student_id]);

        return $this->analytics->compute('/v1/exams/analysis', [
            'max_score' => (float) $exam->total_marks,
            'passing_marks' => (float) $exam->passing_marks,
            'students' => $graded->map(fn (OnlineExamAttempt $attempt): array => [
                'student_id' => $attempt->student_id,
                'score' => (float) $attempt->score,
                'section' => $roster->get($attempt->student_id)['section'] ?? null,
            ])->values()->all(),
            'questions' => $questions->values()->map(fn (OnlineExamQuestion $question, int $index): array => [
                'question_id' => $question->id,
                'number' => $index + 1,
                'marks' => (float) $question->marks,
            ])->all(),
            'responses' => $answers->map(fn (OnlineExamAnswer $answer): array => [
                'student_id' => $attemptStudent->get($answer->online_exam_attempt_id),
                'question_id' => $answer->online_exam_question_id,
                'marks' => (float) $answer->marks_awarded,
            ])->values()->all(),
        ]);
    }

    /**
     * @param  Collection<int, array{exam: OnlineExam, attempt: OnlineExamAttempt}>  $rows
     * @return array{attempts: int, average: float|null, pass_rate: float|null, highest: float|null}
     */
    private function aggregate(Collection $rows): array
    {
        return [
            'attempts' => $rows->count(),
            'average' => $this->round($rows->avg(fn (array $row): float => (float) $row['attempt']->percentage)),
            'pass_rate' => $rows->isEmpty() ? null : round($rows->filter(fn (array $row): bool => $row['attempt']->passed === true)->count() / $rows->count() * 100, 1),
            'highest' => $this->round($rows->max(fn (array $row): float => (float) $row['attempt']->percentage)),
        ];
    }

    /**
     * @param  Collection<int, array{exam: OnlineExam, attempt: OnlineExamAttempt}>  $rows
     * @return list<array<string, mixed>>
     */
    private function studentWise(Collection $rows): array
    {
        $students = $rows->groupBy(fn (array $row): int => $row['attempt']->student_id)
            ->map(function (Collection $group, int $studentId): array {
                $attempt = $group->first()['attempt'];
                $attempt->loadMissing('student:id,first_name,last_name,admission_no');

                return [
                    'student_id' => $studentId,
                    'name' => $attempt->student->full_name,
                    'admission_no' => $attempt->student->admission_no,
                    'exams' => $group->count(),
                    'average' => $this->round($group->avg(fn (array $row): float => (float) $row['attempt']->percentage)),
                    'passed' => $group->filter(fn (array $row): bool => $row['attempt']->passed === true)->count(),
                    'best' => $this->round($group->max(fn (array $row): float => (float) $row['attempt']->percentage)),
                    'lowest' => $this->round($group->min(fn (array $row): float => (float) $row['attempt']->percentage)),
                ];
            })
            ->sortByDesc('average')
            ->values();

        $rank = 0;
        $previous = null;

        return array_values($students->map(function (array $student, int $index) use (&$rank, &$previous): array {
            if ($previous === null || $student['average'] < $previous) {
                $rank = $index + 1;
                $previous = $student['average'];
            }

            return [...$student, 'rank' => $rank];
        })->all());
    }

    /**
     * @return list<int>
     */
    private function studentIdsInSection(int $sectionId, AcademicYear $year): array
    {
        return array_values(Enrollment::query()->where('academic_year_id', $year->id)->where('section_id', $sectionId)->pluck('student_id')->map(intval(...))->all());
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }
}
