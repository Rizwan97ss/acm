<?php

namespace App\Http\Requests\Learning;

use App\Enums\MeetingProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LiveClassRequest extends FormRequest
{
    use AudienceRules;

    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isMethod('POST') ? 'live_classes.create' : 'live_classes.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->audienceRules(),
            'course_id' => ['nullable', 'integer', $this->existsInSchool('courses')],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'provider' => ['required', Rule::enum(MeetingProvider::class)],
            'meeting_url' => ['required', 'url:https', 'max:1000'],
            'starts_at' => ['required', 'date', $this->isMethod('POST') ? 'after:now' : 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkAudience($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['meeting_url.url' => __('Paste the full https:// meeting link.')];
    }
}
