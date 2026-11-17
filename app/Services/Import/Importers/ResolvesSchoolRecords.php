<?php

namespace App\Services\Import\Importers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Import\ImportBatch;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * Finds classes, sections and academic years by the names people type in a spreadsheet
 * (case-insensitive), loading each list once per import.
 */
trait ResolvesSchoolRecords
{
    protected function classNamed(ImportBatch $batch, ?string $name): ?SchoolClass
    {
        if ($name === null) {
            return null;
        }

        /** @var array<string, SchoolClass> $classes */
        $classes = $batch->remember('classes', fn (): array => SchoolClass::query()->get()->keyBy(fn (SchoolClass $class): string => Str::lower($class->name))->all());

        return $classes[Str::lower(trim($name))] ?? null;
    }

    protected function sectionNamed(ImportBatch $batch, SchoolClass $class, ?string $name): ?Section
    {
        if ($name === null) {
            return null;
        }

        /** @var array<string, Section> $sections */
        $sections = $batch->remember('sections', fn (): array => Section::query()->get()->keyBy(fn (Section $section): string => $section->school_class_id.'|'.Str::lower($section->name))->all());

        return $sections[$class->id.'|'.Str::lower(trim($name))] ?? null;
    }

    protected function currentYear(ImportBatch $batch): ?AcademicYear
    {
        return $batch->remember('current-year', fn (): ?AcademicYear => AcademicYear::query()->current()->first());
    }

    protected function yearNamed(ImportBatch $batch, ?string $name): ?AcademicYear
    {
        if ($name === null) {
            return $this->currentYear($batch);
        }

        /** @var array<string, AcademicYear> $years */
        $years = $batch->remember('years', fn (): array => AcademicYear::query()->get()->keyBy(fn (AcademicYear $year): string => Str::lower($year->name))->all());

        return $years[Str::lower(trim($name))] ?? null;
    }

    /**
     * Stored value => label for an enum with a label() method, for CHOICE columns.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, string>
     */
    protected static function choicesOf(string $enum): array
    {
        $choices = [];

        foreach ($enum::cases() as $case) {
            $choices[(string) $case->value] = method_exists($case, 'label') ? $case->label() : (string) $case->value;
        }

        return $choices;
    }
}
