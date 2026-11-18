<?php

namespace App\Services\Learning;

use App\Models\Lesson;
use App\Models\LessonBookmark;
use App\Models\LessonProgress;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Lesson views, completion and bookmarks, and course-level completion percentages.
 */
class LearningProgress
{
    public function recordView(Student $student, Lesson $lesson): LessonProgress
    {
        $progress = LessonProgress::query()->firstOrNew(['lesson_id' => $lesson->id, 'student_id' => $student->id]);

        if ($progress->exists) {
            $progress->views++;
        } else {
            $progress->school_id = $lesson->school_id;
            $progress->first_viewed_at = now();
            $progress->views = 1;
        }

        $progress->last_viewed_at = now();
        $progress->save();

        return $progress;
    }

    public function setCompleted(Student $student, Lesson $lesson, bool $completed): LessonProgress
    {
        $progress = $this->recordView($student, $lesson);
        $progress->forceFill(['completed_at' => $completed ? ($progress->completed_at ?? now()) : null])->save();

        return $progress;
    }

    public function toggleBookmark(Student $student, Lesson $lesson): bool
    {
        $existing = LessonBookmark::query()->where('lesson_id', $lesson->id)->where('student_id', $student->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        LessonBookmark::query()->create(['school_id' => $lesson->school_id, 'lesson_id' => $lesson->id, 'student_id' => $student->id]);

        return true;
    }

    /**
     * Completed and total visible lessons per course for one student.
     *
     * @param  list<int>  $visibleLessonIds
     * @return array<int, array{completed: int, total: int, percent: int}>
     */
    public function byCourse(Student $student, array $visibleLessonIds): array
    {
        if ($visibleLessonIds === []) {
            return [];
        }

        $totals = Lesson::query()->whereIn('id', $visibleLessonIds)
            ->select('course_id', DB::raw('count(*) as total'))
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        $completed = LessonProgress::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->where('lesson_progress.student_id', $student->id)
            ->whereNotNull('lesson_progress.completed_at')
            ->whereIn('lesson_progress.lesson_id', $visibleLessonIds)
            ->select('lessons.course_id', DB::raw('count(*) as done'))
            ->groupBy('lessons.course_id')
            ->pluck('done', 'lessons.course_id');

        $result = [];

        foreach ($totals as $courseId => $total) {
            $done = (int) ($completed[$courseId] ?? 0);
            $result[(int) $courseId] = [
                'completed' => $done,
                'total' => (int) $total,
                'percent' => (int) $total > 0 ? (int) round($done / (int) $total * 100) : 0,
            ];
        }

        return $result;
    }
}
