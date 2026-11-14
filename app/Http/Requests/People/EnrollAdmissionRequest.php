<?php

namespace App\Http\Requests\People;

use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class EnrollAdmissionRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $application = $this->route('admission');

        return $application instanceof AdmissionApplication && $this->user()->can('enroll', $application);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('admission_no'))) {
            $this->merge(['admission_no' => Str::upper(trim($this->input('admission_no')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', $this->existsInSchool('sections')],
            'admission_no' => ['nullable', 'string', 'max:32', 'alpha_dash', $this->uniqueInSchool('students', 'admission_no')],
        ];
    }
}
