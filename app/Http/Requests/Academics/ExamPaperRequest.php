<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Exam;
use App\Models\ExamPaper;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamPaperRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $exam instanceof Exam && $this->user()->can('update', $exam);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Exam $exam */
        $exam = $this->route('exam');
        $paper = $this->route('paper');

        return [
            'school_class_id' => ['required', 'integer', $this->existsInSchool('school_classes')],
            'subject_id' => [
                'required', 'integer', $this->existsInSchool('subjects'),
                Rule::unique('exam_papers')
                    ->where('exam_id', $exam->id)
                    ->where('school_class_id', $this->integer('school_class_id'))
                    ->ignore($paper instanceof ExamPaper ? $paper->id : null),
            ],
            'held_on' => ['nullable', 'date'],
            'max_marks' => ['required', 'numeric', 'min:1', 'max:1000'],
            'pass_marks' => ['required', 'numeric', 'min:0', 'lte:max_marks'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['subject_id.unique' => __('This class already has a paper for that subject.')];
    }

    /**
     * @return array{school_class_id: int, subject_id: int, held_on: string|null, max_marks: float, pass_marks: float}
     */
    public function payload(): array
    {
        return [
            'school_class_id' => $this->integer('school_class_id'),
            'subject_id' => $this->integer('subject_id'),
            'held_on' => $this->filled('held_on') ? $this->string('held_on')->toString() : null,
            'max_marks' => $this->float('max_marks'),
            'pass_marks' => $this->float('pass_marks'),
        ];
    }
}
