<?php

namespace App\Http\Requests\Learning;

use App\Enums\PublishStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseRequest extends FormRequest
{
    use AudienceRules;

    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isMethod('POST') ? 'courses.create' : 'courses.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->audienceRules(),
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'publish_at' => ['nullable', 'date'],
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer', 'distinct', $this->existsInSchool('students')],
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
