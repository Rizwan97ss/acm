<?php

namespace App\Services\Academics;

use App\Models\SchoolClass;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages a class together with its sections and subject allocation.
 */
class SchoolClassService
{
    /**
     * @param  array{name: string, level: int, description: string|null, sections: list<array{id: int|null, name: string, capacity: int|null, room: string|null, class_teacher_id: int|null}>, subjects: list<array{id: int, periods_per_week: int}>}  $data
     */
    public function save(SchoolClass $class, array $data): SchoolClass
    {
        return DB::transaction(function () use ($class, $data): SchoolClass {
            $class->fill([
                'name' => $data['name'],
                'level' => $data['level'],
                'description' => $data['description'],
            ])->save();

            $this->syncSections($class, $data['sections']);
            $this->syncSubjects($class, $data['subjects']);

            return $class;
        });
    }

    public function delete(SchoolClass $class): void
    {
        if (TimetableEntry::query()->whereIn('section_id', $class->sections()->select('id'))->exists()) {
            throw ValidationException::withMessages(['class' => __('This class is used in a timetable. Clear its timetable first.')]);
        }

        $class->delete();
    }

    /**
     * @param  list<array{id: int|null, name: string, capacity: int|null, room: string|null, class_teacher_id: int|null}>  $sections
     */
    private function syncSections(SchoolClass $class, array $sections): void
    {
        $keptIds = array_values(array_filter(array_column($sections, 'id')));
        $removed = $class->sections()->whereNotIn('id', $keptIds);

        if (TimetableEntry::query()->whereIn('section_id', (clone $removed)->select('id'))->exists()) {
            throw ValidationException::withMessages(['sections' => __('A section you removed is used in a timetable. Clear its timetable first.')]);
        }

        $removed->delete();

        foreach ($sections as $section) {
            $model = $section['id'] !== null
                ? $class->sections()->findOrFail($section['id'])
                : $class->sections()->make(['school_id' => $class->school_id]);

            $model->fill([
                'name' => $section['name'],
                'capacity' => $section['capacity'],
                'room' => $section['room'],
                'class_teacher_id' => $section['class_teacher_id'],
            ])->save();
        }
    }

    /**
     * @param  list<array{id: int, periods_per_week: int}>  $subjects
     */
    private function syncSubjects(SchoolClass $class, array $subjects): void
    {
        $subjectIds = array_column($subjects, 'id');

        $stillScheduled = TimetableEntry::query()
            ->whereIn('section_id', $class->sections()->select('id'))
            ->whereNotIn('subject_id', $subjectIds)
            ->with('subject:id,name')
            ->first();

        if ($stillScheduled !== null) {
            throw ValidationException::withMessages([
                'subjects' => __(':subject is scheduled in this class\'s timetable and cannot be removed.', ['subject' => $stillScheduled->subject->name]),
            ]);
        }

        $class->subjects()->sync(collect($subjects)->mapWithKeys(fn (array $subject): array => [
            $subject['id'] => ['periods_per_week' => $subject['periods_per_week']],
        ])->all());
    }
}
