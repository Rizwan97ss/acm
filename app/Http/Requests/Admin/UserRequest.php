<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\Tenancy\Tenancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates creating and updating users, authorized through UserPolicy.
 */
class UserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Authorize before validating so unauthorized users never see validation feedback.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user === null
            ? $this->user()->can('create', User::class)
            : $this->user()->can('update', $user);
    }

    protected function prepareForValidation(): void
    {
        if (config('fortify.lowercase_usernames') && is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($user?->id),
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'password' => $user === null
                ? $this->passwordRules()
                : ['nullable', 'string', Password::default(), 'confirmed'],
            'school_id' => [
                $this->user()->isPlatformUser() ? 'nullable' : 'prohibited',
                'integer',
                Rule::exists('schools', 'id')->whereNull('deleted_at'),
            ],
            'is_active' => ['required', 'boolean'],
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * The validated input, typed for UserService. School-bound actors always
     * place users in their own school.
     *
     * @return array{name: string, email: string, phone: string|null, password: string|null, school_id: int|null, is_active: bool, roles: list<int>}
     */
    public function payload(): array
    {
        $actor = $this->user();

        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'phone' => $this->filled('phone') ? $this->string('phone')->toString() : null,
            'password' => $this->filled('password') ? $this->string('password')->toString() : null,
            'school_id' => match (true) {
                app(Tenancy::class)->isSingle() => app(Tenancy::class)->institute()->id,
                $actor->isPlatformUser() => $this->filled('school_id') ? $this->integer('school_id') : null,
                default => $actor->school_id,
            },
            'is_active' => $this->boolean('is_active'),
            'roles' => array_values(array_map(intval(...), (array) $this->validated('roles', []))),
        ];
    }
}
