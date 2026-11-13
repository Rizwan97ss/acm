<?php

namespace App\Services\Academics;

use App\Models\AcademicYear;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicYearService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Create or update a year and replace its terms. The first year of a school becomes current.
     *
     * @param  array{name: string, starts_on: string, ends_on: string, terms: list<array{id: int|null, name: string, starts_on: string, ends_on: string}>}  $data
     */
    public function save(AcademicYear $year, array $data): AcademicYear
    {
        return DB::transaction(function () use ($year, $data): AcademicYear {
            $year->fill([
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
            ]);

            if (! $year->exists && ! AcademicYear::query()->exists()) {
                $year->is_current = true;
            }

            $year->save();

            $keptIds = [];

            foreach ($data['terms'] as $term) {
                $attributes = ['name' => $term['name'], 'starts_on' => $term['starts_on'], 'ends_on' => $term['ends_on']];

                $model = $term['id'] !== null
                    ? $year->terms()->findOrFail($term['id'])
                    : $year->terms()->make(['school_id' => $year->school_id]);

                $model->fill($attributes)->save();
                $keptIds[] = $model->id;
            }

            $year->terms()->whereNotIn('id', $keptIds)->delete();

            return $year;
        });
    }

    /**
     * Mark the year as the school's current year; exactly one year is current at a time.
     */
    public function makeCurrent(AcademicYear $year): void
    {
        DB::transaction(function () use ($year): void {
            AcademicYear::query()->whereKeyNot($year->id)->where('is_current', true)->update(['is_current' => false]);
            $year->update(['is_current' => true]);
        });

        $this->audit->log('academic_year.made_current', $year);
    }

    public function delete(AcademicYear $year): void
    {
        if ($year->is_current) {
            throw ValidationException::withMessages(['academic_year' => __('The current academic year cannot be deleted.')]);
        }

        if ($year->timetableEntries()->exists()) {
            throw ValidationException::withMessages(['academic_year' => __('This year already has a timetable and cannot be deleted.')]);
        }

        $year->delete();
    }
}
