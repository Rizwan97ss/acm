<?php

namespace App\Services\Import\Importers;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\AdmissionApplication;
use App\Models\User;
use App\Services\Import\ImportBatch;
use App\Services\Import\ImportColumn;
use App\Services\Import\Importer;
use App\Services\People\AdmissionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Loads admission applications into the pending queue, where they are reviewed, approved
 * and enrolled as usual.
 */
class AdmissionImporter implements Importer
{
    use ResolvesSchoolRecords;

    public function __construct(private AdmissionService $admissions) {}

    public function key(): string
    {
        return 'admissions';
    }

    public function title(): string
    {
        return __('Admission applications');
    }

    public function description(): string
    {
        return __('Adds applications to the pending queue for review, approval and enrollment.');
    }

    public function authorize(User $user): bool
    {
        return $user->can('create', AdmissionApplication::class);
    }

    public function columns(): array
    {
        return [
            new ImportColumn('class', 'Class applied for', required: true, hint: 'Exact class name as in Classes & sections.', example: 'Class 1', preview: true),
            new ImportColumn('academic_year', 'Academic year', hint: 'Year name; leave blank for the current year.', example: '2026-27', preview: true),
            new ImportColumn('first_name', 'First name', required: true, example: 'Bilal', preview: true),
            new ImportColumn('last_name', 'Last name', required: true, example: 'Raza', preview: true),
            new ImportColumn('gender', 'Gender', required: true, type: ImportColumn::CHOICE, example: 'Male', choices: self::choicesOf(Gender::class)),
            new ImportColumn('date_of_birth', 'Date of birth', required: true, type: ImportColumn::DATE, example: '2019-11-02', preview: true),
            new ImportColumn('previous_school', 'Previous school', example: 'Little Stars Montessori'),
            new ImportColumn('guardian_name', 'Guardian name', required: true, example: 'Usman Raza', preview: true),
            new ImportColumn('guardian_relationship', 'Guardian relationship', required: true, type: ImportColumn::CHOICE, example: 'Father', choices: self::choicesOf(GuardianRelationship::class)),
            new ImportColumn('guardian_phone', 'Guardian phone', required: true, hint: 'Stored encrypted.', example: '+92 321 5550101', preview: true),
            new ImportColumn('guardian_email', 'Guardian email', example: 'usman@example.com'),
            new ImportColumn('notes', 'Notes'),
        ];
    }

    public function rules(ImportBatch $batch): array
    {
        return [
            'class' => ['required', 'string'],
            'academic_year' => ['nullable', 'string'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['required', 'string', 'max:200'],
            'guardian_relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'guardian_phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function check(array $values, ImportBatch $batch): array
    {
        $errors = [];

        if ($this->classNamed($batch, $values['class']) === null) {
            $errors[] = __('There is no class named “:name”.', ['name' => $values['class']]);
        }

        if ($this->yearNamed($batch, $values['academic_year']) === null) {
            $errors[] = $values['academic_year'] === null
                ? __('No current academic year is set. Fill the Academic year column.')
                : __('There is no academic year named “:name”.', ['name' => $values['academic_year']]);
        }

        return $errors;
    }

    public function import(array $values, ImportBatch $batch): void
    {
        $this->admissions->submit($batch->actor, $batch->schoolId, [
            ...Arr::except($values, ['class', 'academic_year']),
            'guardian_email' => $values['guardian_email'] !== null ? Str::lower($values['guardian_email']) : null,
            'school_class_id' => $this->classNamed($batch, $values['class'])?->id,
            'academic_year_id' => $this->yearNamed($batch, $values['academic_year'])?->id,
        ]);
    }
}
