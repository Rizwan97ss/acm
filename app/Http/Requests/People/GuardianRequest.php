<?php

namespace App\Http\Requests\People;

use App\Models\Guardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class GuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guardian = $this->route('guardian');

        return $guardian instanceof Guardian
            ? $this->user()->can('update', $guardian)
            : $this->user()->can('create', Guardian::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::guardianRules();
    }

    /**
     * Shared with LinkGuardianRequest for creating a guardian inline.
     *
     * @return array<string, list<string>>
     */
    public static function guardianRules(string $prefix = ''): array
    {
        return [
            "{$prefix}first_name" => ['required', 'string', 'max:100'],
            "{$prefix}last_name" => ['required', 'string', 'max:100'],
            "{$prefix}email" => ['nullable', 'email', 'max:255'],
            "{$prefix}phone" => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            "{$prefix}occupation" => ['nullable', 'string', 'max:100'],
            "{$prefix}address" => ['nullable', 'string', 'max:500'],
        ];
    }
}
