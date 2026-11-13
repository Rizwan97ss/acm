<?php

namespace App\Http\Requests\Academics;

use App\Enums\SubjectType;
use App\Models\Subject;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            ? $this->user()->can('update', $subject)
            : $this->user()->can('create', Subject::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('subjects')->where('school_id', app(CurrentSchool::class)->id())->ignore($subject instanceof Subject ? $subject->id : null),
            ],
            'type' => ['required', Rule::enum(SubjectType::class)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
