<?php

namespace App\Http\Requests\People;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StudentRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public const array BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public function authorize(): bool
    {
        $student = $this->student();

        return $student === null
            ? $this->user()->can('create', Student::class)
            : $this->user()->can('update', $student);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'admission_no' => is_string($this->input('admission_no')) ? Str::upper(trim($this->input('admission_no'))) : null,
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : null,
        ], fn (?string $value): bool => $value !== null));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->student();

        return [
            'admission_no' => ['nullable', 'string', 'max:32', 'alpha_dash', $this->uniqueInSchool('students', 'admission_no', $student?->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:32'],
            'blood_group' => ['nullable', Rule::in(self::BLOOD_GROUPS)],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:500'],
            'admission_date' => ['required', 'date'],
            'status' => [$student === null ? 'prohibited' : 'required', Rule::enum(StudentStatus::class)],
            'enrollment' => [$student === null ? 'nullable' : 'prohibited', 'array'],
            'enrollment.academic_year_id' => ['required_with:enrollment.section_id', 'nullable', 'integer', $this->existsInSchool('academic_years')],
            'enrollment.section_id' => ['nullable', 'integer', $this->existsInSchool('sections')],
        ];
    }

    /**
     * National IDs are unique within the school, checked through the blind index.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $nationalId = $this->input('national_id');

                if (! is_string($nationalId) || $nationalId === '') {
                    return;
                }

                $duplicate = Student::query()
                    ->whereBlindIndex('national_id', $nationalId)
                    ->when($this->student(), fn ($query, Student $student) => $query->whereKeyNot($student->id))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('national_id', __('Another student already has this national ID.'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesPayload(): array
    {
        return Arr::except($this->validated(), ['enrollment']);
    }

    /**
     * @return array{academic_year_id: int, section_id: int}|null
     */
    public function enrollmentPayload(): ?array
    {
        if (! $this->filled('enrollment.section_id')) {
            return null;
        }

        return [
            'academic_year_id' => $this->integer('enrollment.academic_year_id'),
            'section_id' => $this->integer('enrollment.section_id'),
        ];
    }

    private function student(): ?Student
    {
        $student = $this->route('student');

        return $student instanceof Student ? $student : null;
    }
}
