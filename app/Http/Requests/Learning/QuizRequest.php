<?php

namespace App\Http\Requests\Learning;

use App\Enums\PublishStatus;
use App\Enums\QuizType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuizRequest extends FormRequest
{
    use AudienceRules;

    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isMethod('POST') ? 'quizzes.create' : 'quizzes.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->audienceRules(),
            'course_id' => ['nullable', 'integer', $this->existsInSchool('courses')],
            'type' => ['required', Rule::enum(QuizType::class)],
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'shuffle_questions' => ['boolean'],
            'show_results' => ['boolean'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkAudience($validator)];
    }
}
