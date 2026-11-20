<?php

namespace App\Http\Requests\Academics;

use App\Enums\QuestionDifficulty;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A question bank item: an exam question plus its subject, optional class, difficulty
 * and topic.
 */
class BankQuestionRequest extends ExamQuestionRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isMethod('POST') ? 'question_bank.create' : 'question_bank.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'subject_id' => ['required', 'integer', $this->existsInSchool('subjects')],
            'school_class_id' => ['nullable', 'integer', $this->existsInSchool('school_classes')],
            'difficulty' => ['required', Rule::enum(QuestionDifficulty::class)],
            'topic' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bankPayload(): array
    {
        return [
            ...$this->payload(),
            'subject_id' => $this->integer('subject_id'),
            'school_class_id' => $this->filled('school_class_id') ? $this->integer('school_class_id') : null,
            'difficulty' => (string) $this->input('difficulty'),
            'topic' => $this->input('topic'),
        ];
    }
}
