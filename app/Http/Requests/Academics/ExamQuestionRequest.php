<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Learning\QuizQuestionRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An exam question: the same shapes as quiz questions, plus the marks lost for a wrong
 * objective answer when the exam uses negative marking.
 */
class ExamQuestionRequest extends QuizQuestionRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('online_exams.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'negative_marks' => ['nullable', 'numeric', 'min:0', 'lte:marks'],
        ];
    }

    /**
     * @return array{type: string, prompt: string, marks: float, explanation: string|null, options: list<array{id: string, text: string}>|null, answer_key: list<string>|null, negative_marks: float}
     */
    public function payload(): array
    {
        return [...parent::payload(), 'negative_marks' => (float) ($this->input('negative_marks') ?? 0)];
    }
}
