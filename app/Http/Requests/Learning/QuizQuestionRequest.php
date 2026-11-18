<?php

namespace App\Http\Requests\Learning;

use App\Enums\QuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Choice questions need 2–8 options and at least one correct one (exactly one for single
 * choice). True/false needs the right answer. Short answers may list accepted answers.
 */
class QuizQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('quizzes.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'prompt' => ['required', 'string', 'max:5000'],
            'marks' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array', 'max:8'],
            'options.*.text' => ['required', 'string', 'max:500'],
            'options.*.correct' => ['boolean'],
            'correct' => ['nullable', 'in:true,false'],
            'accepted_answers' => ['nullable', 'array', 'max:10'],
            'accepted_answers.*' => ['string', 'max:200'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = QuestionType::tryFrom((string) $this->input('type'));

            if ($type === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            if ($type->hasOptions()) {
                $options = (array) $this->input('options', []);
                $correct = count(array_filter($options, fn (mixed $option): bool => is_array($option) && filter_var($option['correct'] ?? false, FILTER_VALIDATE_BOOL)));

                if (count($options) < 2) {
                    $validator->errors()->add('options', __('Add at least two options.'));
                } elseif ($correct === 0) {
                    $validator->errors()->add('options', __('Mark the correct option.'));
                } elseif ($type === QuestionType::SingleChoice && $correct > 1) {
                    $validator->errors()->add('options', __('A single-choice question has exactly one correct option.'));
                }
            }

            if ($type === QuestionType::TrueFalse && ! $this->filled('correct')) {
                $validator->errors()->add('correct', __('Choose whether the statement is true or false.'));
            }
        }];
    }

    /**
     * @return array{type: string, prompt: string, marks: float, explanation: string|null, options: list<array{id: string, text: string}>|null, answer_key: list<string>|null}
     */
    public function payload(): array
    {
        $type = QuestionType::from((string) $this->input('type'));
        $options = null;
        $key = null;

        if ($type->hasOptions()) {
            $options = [];
            $key = [];

            foreach (array_values((array) $this->input('options', [])) as $index => $option) {
                $id = chr(97 + $index);
                $options[] = ['id' => $id, 'text' => (string) $option['text']];

                if (filter_var($option['correct'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $key[] = $id;
                }
            }
        } elseif ($type === QuestionType::TrueFalse) {
            $key = [(string) $this->input('correct')];
        } elseif ($type === QuestionType::ShortAnswer) {
            $accepted = array_values(array_filter(array_map(fn (mixed $answer): string => trim((string) $answer), (array) $this->input('accepted_answers', []))));
            $key = $accepted === [] ? null : $accepted;
        }

        return [
            'type' => $type->value,
            'prompt' => (string) $this->input('prompt'),
            'marks' => (float) $this->input('marks'),
            'explanation' => $this->input('explanation'),
            'options' => $options,
            'answer_key' => $key,
        ];
    }
}
