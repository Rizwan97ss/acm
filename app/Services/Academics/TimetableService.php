<?php

namespace App\Services\Academics;

use App\Models\Period;
use App\Models\Section;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Places lessons on the timetable, refusing clashes for the section, teacher and room.
 */
class TimetableService
{
    /**
     * Create or update the lesson in a section's slot.
     *
     * @param  array{academic_year_id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int|null, room: string|null}  $data
     */
    public function place(TimetableEntry $entry, array $data): TimetableEntry
    {
        return DB::transaction(function () use ($entry, $data): TimetableEntry {
            $this->guard($entry, $data);

            $entry->fill($data);
            $entry->school_id ??= Section::query()->findOrFail($data['section_id'])->school_id;
            $entry->save();

            return $entry;
        });
    }

    /**
     * @param  array{academic_year_id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int|null, room: string|null}  $data
     */
    private function guard(TimetableEntry $entry, array $data): void
    {
        $period = Period::query()->findOrFail($data['period_id']);

        if ($period->is_break) {
            throw ValidationException::withMessages(['period_id' => __('Lessons cannot be scheduled during a break.')]);
        }

        $section = Section::query()->with('schoolClass')->findOrFail($data['section_id']);

        if (! $section->schoolClass->subjects()->whereKey($data['subject_id'])->exists()) {
            throw ValidationException::withMessages(['subject_id' => __('This subject is not taught in :class.', ['class' => $section->schoolClass->name])]);
        }

        $slot = TimetableEntry::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('period_id', $data['period_id'])
            ->when($entry->exists, fn ($query) => $query->whereKeyNot($entry->id));

        if ((clone $slot)->where('section_id', $data['section_id'])->exists()) {
            throw ValidationException::withMessages(['period_id' => __('This section already has a lesson in that slot.')]);
        }

        if ($data['teacher_id'] !== null) {
            $clash = (clone $slot)->where('teacher_id', $data['teacher_id'])->with('section.schoolClass')->first();

            if ($clash !== null) {
                throw ValidationException::withMessages([
                    'teacher_id' => __('This teacher is already teaching :section in that slot.', ['section' => $clash->section->fullName()]),
                ]);
            }
        }

        if ($data['room'] !== null) {
            $clash = (clone $slot)->where('room', $data['room'])->with('section.schoolClass')->first();

            if ($clash !== null) {
                throw ValidationException::withMessages([
                    'room' => __('Room :room is already used by :section in that slot.', ['room' => $data['room'], 'section' => $clash->section->fullName()]),
                ]);
            }
        }
    }
}
