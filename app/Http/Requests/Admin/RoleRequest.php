<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates role input, authorized through RolePolicy. Escalation checks live in RoleService.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return $role === null
            ? $this->user()->can('create', Role::class)
            : $this->user()->can('update', $role);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(PermissionRegistry::class)->all())],
        ];
    }

    /**
     * The validated input, typed for RoleService.
     *
     * @return array{name: string, description: string|null, parent_id: int|null, permissions: list<string>}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'permissions' => array_values(array_map(strval(...), (array) $this->validated('permissions', []))),
        ];
    }
}
