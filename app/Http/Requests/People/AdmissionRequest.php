<?php

namespace App\Http\Requests\People;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdmissionRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $application = $this->route('admission');

        return $application instanceof AdmissionApplication
            ? $this->user()->can('update', $application)
            : $this->user()->can('create', AdmissionApplication::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('guardian_email'))) {
            $this->merge(['guardian_email' => Str::lower(trim($this->input('guardian_email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
            'school_class_id' => ['required', 'integer', $this->existsInSchool('school_classes')],
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
}
