<?php

namespace App\Services\Academics;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Support\Collection;

/**
 * Dropdown options shared by every page that picks a year, class or section.
 */
class AcademicOptions
{
    /**
     * @return Collection<int, array{id: int, name: string, is_current: bool}>
     */
    public function years(): Collection
    {
        return AcademicYear::query()
            ->orderByDesc('starts_on')
            ->get(['id', 'name', 'is_current'])
            ->map(fn (AcademicYear $year): array => ['id' => $year->id, 'name' => $year->name, 'is_current' => $year->is_current]);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function classes(): Collection
    {
        return SchoolClass::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SchoolClass $class): array => ['id' => $class->id, 'name' => $class->name]);
    }

    /**
     * Sections ordered by class level, labelled "Grade 5 – A".
     *
     * @return Collection<int, array{id: int, name: string, school_class_id: int, room: string|null, capacity: int|null}>
     */
    public function sections(): Collection
    {
        return Section::query()
            ->with('schoolClass:id,name,level')
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d %s %s', $section->schoolClass->level, $section->schoolClass->name, $section->name))
            ->values()
            ->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->fullName(),
                'school_class_id' => $section->school_class_id,
                'room' => $section->room,
                'capacity' => $section->capacity,
            ]);
    }
}
