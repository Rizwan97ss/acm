<?php

namespace App\Http\Requests\Learning;

use App\Enums\PublishStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('courses.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:60000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'publish_at' => ['nullable', 'date'],
        ];
    }
}
