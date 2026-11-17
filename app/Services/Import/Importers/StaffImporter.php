<?php

namespace App\Services\Import\Importers;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Models\Employee;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Services\Import\ImportBatch;
use App\Services\Import\ImportColumn;
use App\Services\Import\Importer;
use App\Services\People\ReferenceNumberGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Teaching and non-teaching staff. Employee numbers are generated when left blank. Users who
 * hold only teachers.create can import teaching staff only.
 */
class StaffImporter implements Importer
{
    use ResolvesSchoolRecords;

    public function __construct(
        private ReferenceNumberGenerator $numbers,
        private EmployeePolicy $policy,
    ) {}

    public function key(): string
    {
        return 'staff';
    }

    public function title(): string
    {
        return __('Staff');
    }

    public function description(): string
    {
        return __('Teaching and non-teaching staff records. Employee numbers are generated when left blank.');
    }

    public function authorize(User $user): bool
    {
        return $user->hasPermission('employees.create') || $user->hasPermission('teachers.create');
    }

    public function columns(): array
    {
        return [
            new ImportColumn('employee_no', 'Employee no', type: ImportColumn::CODE, hint: 'Leave blank to generate one automatically.', example: 'GHS-E0101'),
            new ImportColumn('first_name', 'First name', required: true, example: 'Sara', preview: true),
            new ImportColumn('last_name', 'Last name', required: true, example: 'Ahmed', preview: true),
            new ImportColumn('is_teaching', 'Teaching', required: true, type: ImportColumn::BOOLEAN, hint: 'Yes for teachers, No for other staff.', example: 'Yes', preview: true),
            new ImportColumn('designation', 'Designation', required: true, example: 'Mathematics teacher', preview: true),
            new ImportColumn('department', 'Department', example: 'Science', preview: true),
            new ImportColumn('employment_type', 'Employment type', required: true, type: ImportColumn::CHOICE, example: 'Full time', choices: self::choicesOf(EmploymentType::class)),
            new ImportColumn('joined_on', 'Joined on', required: true, type: ImportColumn::DATE, example: '2024-08-01', preview: true),
            new ImportColumn('qualification', 'Qualification', example: 'M.Sc. Mathematics'),
            new ImportColumn('gender', 'Gender', type: ImportColumn::CHOICE, example: 'Female', choices: self::choicesOf(Gender::class)),
            new ImportColumn('date_of_birth', 'Date of birth', type: ImportColumn::DATE, example: '1990-03-15'),
            new ImportColumn('email', 'Email', example: 'sara@example.com'),
            new ImportColumn('phone', 'Phone', hint: 'Stored encrypted.', example: '+92 300 7654321'),
            new ImportColumn('national_id', 'National ID', hint: 'Stored encrypted.'),
            new ImportColumn('bank_account', 'Bank account', hint: 'Stored encrypted.'),
            new ImportColumn('address', 'Address', hint: 'Stored encrypted.'),
        ];
    }

    public function rules(ImportBatch $batch): array
    {
        return [
            'employee_no' => ['nullable', 'string', 'max:32', 'alpha_dash', Rule::unique('employees', 'employee_no')->where('school_id', $batch->schoolId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'is_teaching' => ['required', 'boolean'],
            'designation' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'joined_on' => ['required', 'date'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'national_id' => ['nullable', 'string', 'max:32'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function check(array $values, ImportBatch $batch): array
    {
        $errors = [];

        if ($line = $batch->duplicateOf('employee_no', $values['employee_no'])) {
            $errors[] = __('Employee no :value is also used on row :line.', ['value' => $values['employee_no'], 'line' => $line]);
        }

        if (! $this->policy->manageKind($batch->actor, $values['is_teaching'] === '1', 'create')) {
            $errors[] = __('You can only import teaching staff.');
        }

        return $errors;
    }

    public function import(array $values, ImportBatch $batch): void
    {
        $attributes = [
            ...Arr::except($values, ['employee_no']),
            'email' => $values['email'] !== null ? Str::lower($values['email']) : null,
            'is_teaching' => $values['is_teaching'] === '1',
            'status' => EmployeeStatus::Active,
            'school_id' => $batch->schoolId,
        ];

        $create = fn (string $number): Employee => Employee::query()->create([...$attributes, 'employee_no' => $number]);

        $values['employee_no'] !== null
            ? $create($values['employee_no'])
            : $this->numbers->withNext('employee', $batch->schoolId, $create);
    }
}
