<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Learning\AudienceRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Settings of an online exam: audience (class, optional section, optional chosen
 * students), window, duration, marks and options. Release and results have their own
 * actions.
 */
class OnlineExamRequest extends FormRequest
{
    use AudienceRules;

    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isMethod('POST') ? 'online_exams.create' : 'online_exams.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->audienceRules(),
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'total_marks' => ['required', 'numeric', 'min:1', 'max:1000'],
            'passing_marks' => ['required', 'numeric', 'min:0', 'lte:total_marks'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'negative_marking' => ['boolean'],
            'show_rank' => ['boolean'],
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->checkAudience($validator),
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at', 'duration_minutes'])) {
                    return;
                }

                $window = Carbon::parse((string) $this->input('starts_at'))->diffInMinutes(Carbon::parse((string) $this->input('ends_at')));

                if ($this->integer('duration_minutes') > $window) {
                    $validator->errors()->add('duration_minutes', __('The duration is longer than the exam window (:minutes minutes).', ['minutes' => (int) $window]));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return collect($this->validated())->except('student_ids')->all();
    }

    /**
     * @return list<int>
     */
    public function studentIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('student_ids', [])));
    }
}
