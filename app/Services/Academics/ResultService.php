<?php

namespace App\Services\Academics;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Turns marks into results: totals, percentages, grades, pass/fail and section ranks.
 *
 * A student passes when they pass every paper. Ranks use standard competition ranking
 * ("1, 2, 2, 4") on percentage, and only students with every mark entered are ranked.
 */
class ResultService
{
    /**
     * @return array{papers: list<array{id: int, subject: string, code: string, max_marks: float, pass_marks: float}>, rows: list<non-empty-array<string, mixed>>}
     */
    public function forSection(Exam $exam, Section $section): array
    {
        $exam->loadMissing('gradeScale.bands');

        $papers = ExamPaper::query()
            ->with('subject:id,name,code')
            ->where('exam_id', $exam->id)
            ->where('school_class_id', $section->school_class_id)
            ->get()
            ->sortBy(fn (ExamPaper $paper): string => $paper->subject->name)
            ->values();

        $enrollments = Enrollment::query()
            ->with('student:id,first_name,last_name,admission_no')
            ->where('section_id', $section->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('status', EnrollmentStatus::Active)
            ->orderByRaw('roll_number is null')
            ->orderBy('roll_number')
            ->get();

        $marks = Mark::query()
            ->whereIn('exam_paper_id', $papers->pluck('id'))
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->groupBy('student_id');

        $rows = $enrollments->map(fn (Enrollment $enrollment): array => [
            'student' => [
                'id' => $enrollment->student->id,
                'name' => $enrollment->student->full_name,
                'admission_no' => $enrollment->student->admission_no,
                'roll_number' => $enrollment->roll_number,
            ],
            ...$this->computeRow($exam->gradeScale, $papers, $marks->get($enrollment->student_id, collect())),
        ]);

        return [
            'papers' => array_values($papers->map(fn (ExamPaper $paper): array => [
                'id' => $paper->id,
                'subject' => $paper->subject->name,
                'code' => $paper->subject->code,
                'max_marks' => (float) $paper->max_marks,
                'pass_marks' => (float) $paper->pass_marks,
            ])->all()),
            'rows' => array_values($this->rank($rows)->all()),
        ];
    }

    /**
     * One student's result with their rank in the section.
     *
     * @return array<string, mixed>|null
     */
    public function forStudent(Exam $exam, Student $student): ?array
    {
        $enrollment = Enrollment::query()
            ->with('section.schoolClass')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->first();

        if ($enrollment === null) {
            return null;
        }

        $sectionResults = $this->forSection($exam, $enrollment->section);
        $row = collect($sectionResults['rows'])->firstWhere('student.id', $student->id);

        return $row === null ? null : [
            ...$row,
            'section' => $enrollment->section->fullName(),
            'class_size' => count($sectionResults['rows']),
            'papers' => $sectionResults['papers'],
        ];
    }

    /**
     * @param  Collection<int, ExamPaper>  $papers
     * @param  Collection<int, Mark>  $marks
     * @return array<string, mixed>
     */
    private function computeRow(GradeScale $scale, Collection $papers, Collection $marks): array
    {
        $byPaper = $marks->keyBy('exam_paper_id');
        $total = 0.0;
        $maxTotal = 0.0;
        $complete = true;
        $passedAll = true;
        $subjects = [];

        foreach ($papers as $paper) {
            $mark = $byPaper->get($paper->id);
            $max = (float) $paper->max_marks;
            $obtained = $mark?->marks_obtained !== null ? (float) $mark->marks_obtained : null;
            $absent = (bool) $mark?->is_absent;

            if ($mark === null || ($obtained === null && ! $absent)) {
                $complete = false;
            }

            $scored = $obtained ?? 0.0;
            $total += $scored;
            $maxTotal += $max;

            $passed = ! $absent && $obtained !== null && $obtained >= (float) $paper->pass_marks;
            $passedAll = $passedAll && $passed;
            $band = $obtained !== null && $max > 0 ? $scale->bandFor($obtained / $max * 100) : null;

            $subjects[$paper->id] = [
                'marks' => $obtained,
                'is_absent' => $absent,
                'grade' => $absent ? 'AB' : $band?->grade,
                'passed' => $passed,
                'remarks' => $mark?->remarks,
            ];
        }

        $percentage = $maxTotal > 0 ? round($total / $maxTotal * 100, 2) : null;
        $band = $percentage !== null ? $scale->bandFor($percentage) : null;

        return [
            'subjects' => $subjects,
            'total' => round($total, 2),
            'max_total' => $maxTotal,
            'percentage' => $percentage,
            'grade' => $complete ? $band?->grade : null,
            'grade_point' => $complete && $band?->grade_point !== null ? (float) $band->grade_point : null,
            'remark' => $complete ? $band?->remark : null,
            'complete' => $complete && $papers->isNotEmpty(),
            'passed' => $complete && $papers->isNotEmpty() ? $passedAll : null,
            'rank' => null,
        ];
    }

    /**
     * @param  Collection<int, non-empty-array<string, mixed>>  $rows
     * @return Collection<int, non-empty-array<string, mixed>>
     */
    private function rank(Collection $rows): Collection
    {
        $ranked = $rows->filter(fn (array $row): bool => $row['complete'])->sortByDesc('percentage')->values();
        $ranks = [];
        $previous = null;
        $rank = 0;

        foreach ($ranked as $position => $row) {
            if ($row['percentage'] !== $previous) {
                $rank = $position + 1;
                $previous = $row['percentage'];
            }

            $ranks[$row['student']['id']] = $rank;
        }

        return $rows->map(fn (array $row): array => [...$row, 'rank' => $ranks[$row['student']['id']] ?? null]);
    }
}
