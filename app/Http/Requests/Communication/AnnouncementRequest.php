<?php

namespace App\Http\Requests\Communication;

use App\Enums\Audience;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Announcement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        return $this->route('announcement') instanceof Announcement
            ? $this->user()->can('announcements.edit')
            : $this->user()->can('announcements.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::enum(Audience::class)],
            'school_class_id' => [
                'nullable', 'integer', $this->existsInSchool('school_classes'),
                Rule::prohibitedIf(in_array($this->input('audience'), [Audience::Everyone->value, Audience::Staff->value], true)),
            ],
            'is_pinned' => ['boolean'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'publish' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['school_class_id.prohibited' => __('A class can only be chosen for students or guardians.')];
    }

    /**
     * @return array{title: string, body: string, audience: string, school_class_id: int|null, is_pinned: bool, expires_on: string|null}
     */
    public function payload(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'body' => (string) $this->validated('body'),
            'audience' => (string) $this->validated('audience'),
            'school_class_id' => $this->validated('school_class_id') !== null ? (int) $this->validated('school_class_id') : null,
            'is_pinned' => $this->boolean('is_pinned'),
            'expires_on' => $this->validated('expires_on'),
        ];
    }
}
