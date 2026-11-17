<?php

namespace App\Services\Import\Importers;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Requests\People\StudentRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Import\ImportBatch;
use App\Services\Import\ImportColumn;
use App\Services\Import\Importer;
use App\Services\People\GuardianService;
use App\Services\People\StudentService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * One row per student, optionally enrolled in a class/section of the current academic year
 * and linked to a primary parent. Parents are matched by phone or email, so siblings on
 * different rows share one guardian record.
 */
class StudentImporter implements Importer
{
    use ResolvesSchoolRecords;

    private const array PARENT_COLUMNS = ['parent_first_name', 'parent_last_name', 'parent_relationship', 'parent_phone', 'parent_email', 'parent_occupation', 'parent_address'];

    public function __construct(
        private StudentService $students,
        private GuardianService $guardians,
    ) {}

    public function key(): string
    {
        return 'students';
    }

    public function title(): string
    {
        return __('Students and parents');
    }

    public function description(): string
    {
        return __('One row per student. Optionally places them in a class and section of the current academic year and adds their primary parent.');
    }

    public function authorize(User $user): bool
    {
        return $user->can('create', Student::class);
    }

    public function columns(): array
    {
        return [
            new ImportColumn('admission_no', 'Admission no', type: ImportColumn::CODE, hint: 'Leave blank to generate one automatically.', example: 'GHS260101'),
            new ImportColumn('first_name', 'First name', required: true, example: 'Aisha', preview: true),
            new ImportColumn('last_name', 'Last name', required: true, example: 'Khan', preview: true),
            new ImportColumn('gender', 'Gender', required: true, type: ImportColumn::CHOICE, example: 'Female', choices: self::choicesOf(Gender::class), preview: true),
            new ImportColumn('date_of_birth', 'Date of birth', required: true, type: ImportColumn::DATE, example: '2015-06-21', preview: true),
            new ImportColumn('admission_date', 'Admission date', type: ImportColumn::DATE, hint: 'Leave blank for today.', example: '2026-04-01'),
            new ImportColumn('class', 'Class', hint: 'Exact class name as in Classes & sections. Needs a current academic year.', example: 'Class 1', preview: true),
            new ImportColumn('section', 'Section', hint: 'Section name within that class; required when Class is filled.', example: 'A', preview: true),
            new ImportColumn('email', 'Email', example: 'aisha@example.com'),
            new ImportColumn('national_id', 'National ID', hint: 'Stored encrypted.'),
            new ImportColumn('blood_group', 'Blood group', type: ImportColumn::CODE, hint: 'One of: '.implode(', ', StudentRequest::BLOOD_GROUPS).'.', example: 'B+'),
            new ImportColumn('medical_notes', 'Medical notes', hint: 'Stored encrypted.'),
            new ImportColumn('address', 'Address', hint: 'Stored encrypted.'),
            new ImportColumn('parent_first_name', 'Parent first name', hint: 'Fill the parent columns to add or link the primary parent.', example: 'Imran', preview: true),
            new ImportColumn('parent_last_name', 'Parent last name', example: 'Khan'),
            new ImportColumn('parent_relationship', 'Parent relationship', type: ImportColumn::CHOICE, example: 'Father', choices: self::choicesOf(GuardianRelationship::class)),
            new ImportColumn('parent_phone', 'Parent phone', hint: 'An existing parent with this phone or email is reused (siblings).', example: '+92 300 1234567', preview: true),
            new ImportColumn('parent_email', 'Parent email', example: 'imran@example.com'),
            new ImportColumn('parent_occupation', 'Parent occupation', example: 'Engineer'),
            new ImportColumn('parent_address', 'Parent address'),
        ];
    }

    public function rules(ImportBatch $batch): array
    {
        $parentGiven = 'required_with:'.implode(',', self::PARENT_COLUMNS);

        return [
            'admission_no' => ['nullable', 'string', 'max:32', 'alpha_dash', Rule::unique('students', 'admission_no')->where('school_id', $batch->schoolId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'admission_date' => ['nullable', 'date'],
            'class' => ['nullable', 'string', 'required_with:section'],
            'section' => ['nullable', 'string', 'required_with:class'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:32'],
            'blood_group' => ['nullable', Rule::in(StudentRequest::BLOOD_GROUPS)],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:500'],
            'parent_first_name' => ['nullable', $parentGiven, 'string', 'max:100'],
            'parent_last_name' => ['nullable', $parentGiven, 'string', 'max:100'],
            'parent_relationship' => ['nullable', $parentGiven, Rule::enum(GuardianRelationship::class)],
            'parent_phone' => ['nullable', $parentGiven, 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_occupation' => ['nullable', 'string', 'max:100'],
            'parent_address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function check(array $values, ImportBatch $batch): array
    {
        $errors = [];

        if ($line = $batch->duplicateOf('admission_no', $values['admission_no'])) {
            $errors[] = __('Admission no :value is also used on row :line.', ['value' => $values['admission_no'], 'line' => $line]);
        }

        if ($values['national_id'] !== null) {
            if ($line = $batch->duplicateOf('national_id', $values['national_id'])) {
                $errors[] = __('This national ID is also used on row :line.', ['line' => $line]);
            } elseif (Student::query()->whereBlindIndex('national_id', $values['national_id'])->exists()) {
                $errors[] = __('Another student already has this national ID.');
            }
        }

        if ($values['class'] !== null) {
            $class = $this->classNamed($batch, $values['class']);

            if ($class === null) {
                $errors[] = __('There is no class named “:name”.', ['name' => $values['class']]);
            } elseif ($this->sectionNamed($batch, $class, $values['section']) === null) {
                $errors[] = __('Class :class has no section named “:name”.', ['class' => $class->name, 'name' => $values['section']]);
            } elseif ($this->currentYear($batch) === null) {
                $errors[] = __('Students can only be placed in a class once a current academic year is set.');
            }
        }

        if ($this->hasParent($values) && ! $batch->actor->can('create', Guardian::class) && $this->guardians->findMatching($values['parent_phone'], $values['parent_email']) === null) {
            $errors[] = __('You are not allowed to add new parents. Leave the parent columns empty.');
        }

        return $errors;
    }

    public function import(array $values, ImportBatch $batch): void
    {
        $enrollment = null;

        if ($values['class'] !== null) {
            $class = $this->classNamed($batch, $values['class']);
            $section = $class !== null ? $this->sectionNamed($batch, $class, $values['section']) : null;
            $year = $this->currentYear($batch);

            if ($section !== null && $year !== null) {
                $enrollment = ['academic_year_id' => $year->id, 'section_id' => $section->id];
            }
        }

        $student = $this->students->create($batch->schoolId, [
            ...Arr::only($values, ['admission_no', 'first_name', 'last_name', 'gender', 'date_of_birth', 'national_id', 'blood_group', 'medical_notes', 'address']),
            'admission_no' => $values['admission_no'] !== null ? Str::upper($values['admission_no']) : null,
            'email' => $values['email'] !== null ? Str::lower($values['email']) : null,
            'admission_date' => $values['admission_date'] ?? now()->toDateString(),
        ], $enrollment);

        if (! $this->hasParent($values)) {
            return;
        }

        $guardian = $this->guardians->findMatching($values['parent_phone'], $values['parent_email'])
            ?? Guardian::query()->create([
                'school_id' => $batch->schoolId,
                'first_name' => $values['parent_first_name'],
                'last_name' => $values['parent_last_name'],
                'email' => $values['parent_email'] !== null ? Str::lower($values['parent_email']) : null,
                'phone' => $values['parent_phone'],
                'occupation' => $values['parent_occupation'],
                'address' => $values['parent_address'],
            ]);

        $this->guardians->link($student, $guardian, GuardianRelationship::from((string) $values['parent_relationship']), true);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function hasParent(array $values): bool
    {
        return collect(self::PARENT_COLUMNS)->contains(fn (string $column): bool => $values[$column] !== null);
    }
}
