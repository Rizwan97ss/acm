<?php

namespace App\Services\Rbac;

use Illuminate\Support\Str;

/**
 * The single source of truth for which permissions exist, read from config/rbac.php.
 */
class PermissionRegistry
{
    /**
     * Get all modules keyed by module name.
     *
     * @return array<string, array{label: string, group: string, actions: list<string>}>
     */
    public function modules(): array
    {
        return config('rbac.modules', []);
    }

    /**
     * Get every permission name ("module.action").
     *
     * @return list<string>
     */
    public function all(): array
    {
        $names = [];

        foreach ($this->modules() as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * Determine whether the given permission is declared.
     */
    public function exists(string $permission): bool
    {
        return in_array($permission, $this->all(), true);
    }

    /**
     * Expand wildcard patterns ("*", "students.*", "*.view") into concrete permission names.
     *
     * @param  list<string>  $patterns
     * @param  list<string>  $except
     * @return list<string>
     */
    public function expand(array $patterns, array $except = []): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (string $permission): bool => Str::is($patterns, $permission) && ! Str::is($except, $permission),
        ));
    }
}
