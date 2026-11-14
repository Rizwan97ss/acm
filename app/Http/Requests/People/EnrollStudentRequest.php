<?php

namespace App\Http\Requests\People;

use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EnrollStudentRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && $this->user()->can('update', $student);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
            'section_id' => ['required', 'integer', $this->existsInSchool('sections')],
            'roll_number' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
