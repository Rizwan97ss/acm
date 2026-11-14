<?php

namespace App\Http\Requests\People;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee === null
            ? $this->user()->can('create', Employee::class)
            : $this->user()->can('update', $employee);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'employee_no' => is_string($this->input('employee_no')) ? Str::upper(trim($this->input('employee_no'))) : null,
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : null,
        ], fn (?string $value): bool => $value !== null));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->employee();

        return [
            'employee_no' => ['nullable', 'string', 'max:32', 'alpha_dash', $this->uniqueInSchool('employees', 'employee_no', $employee?->id)],
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('school_id', $this->schoolId()),
                Rule::unique('employees', 'user_id')->ignore($employee?->id),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'national_id' => ['nullable', 'string', 'max:32'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_teaching' => ['required', 'boolean'],
            'designation' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'joined_on' => ['required', 'date'],
            'left_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
        ];
    }

    /**
     * Users holding only teachers.* may only manage teaching staff.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $action = $this->employee() === null ? 'create' : 'edit';

                if (! app(EmployeePolicy::class)->manageKind($this->user(), $this->boolean('is_teaching'), $action)) {
                    $validator->errors()->add('is_teaching', __('You can only manage teaching staff.'));
                }
            },
        ];
    }

    private function employee(): ?Employee
    {
        $employee = $this->route('staff');

        return $employee instanceof Employee ? $employee : null;
    }
}
