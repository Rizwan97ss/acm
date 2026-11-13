<?php

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates school input, authorized through SchoolPolicy.
 */
class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var School|null $school */
        $school = $this->route('school');

        return $school === null
            ? $this->user()->can('create', School::class)
            : $this->user()->can('update', $school);
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
        /** @var School|null $school */
        $school = $this->route('school');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique(School::class)->ignore($school?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                $this->user()->isPlatformUser() ? 'nullable' : 'prohibited',
                'integer',
                Rule::exists('schools', 'id')->whereNull('deleted_at')->whereNull('parent_id'),
                Rule::notIn(array_filter([$school?->id])),
            ],
            'is_active' => ['required', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['boolean'],
        ];
    }
}
