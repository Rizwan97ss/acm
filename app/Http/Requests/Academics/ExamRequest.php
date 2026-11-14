<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Exam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExamRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $exam instanceof Exam
            ? $this->user()->can('update', $exam)
            : $this->user()->can('create', Exam::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
            'term_id' => ['nullable', 'integer', $this->existsInSchool('terms')->where('academic_year_id', $this->integer('academic_year_id'))],
            'grade_scale_id' => ['required', 'integer', $this->existsInSchool('grade_scales')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * @return array{academic_year_id: int, term_id: int|null, grade_scale_id: int, name: string, starts_on: string, ends_on: string}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'academic_year_id' => $this->integer('academic_year_id'),
            'term_id' => $this->filled('term_id') ? $this->integer('term_id') : null,
            'grade_scale_id' => $this->integer('grade_scale_id'),
            'starts_on' => $this->string('starts_on')->toString(),
            'ends_on' => $this->string('ends_on')->toString(),
        ];
    }
}
