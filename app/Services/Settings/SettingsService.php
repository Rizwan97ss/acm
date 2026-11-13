<?php

namespace App\Services\Settings;

use App\Enums\SettingScope;
use App\Models\Setting;
use App\Models\User;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Reads and writes registry-defined settings with user -> school -> global -> default resolution.
 */
class SettingsService
{
    public function __construct(
        private Cache $cache,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * Get every group definition.
     *
     * @return array<string, array{label: string, description?: string, settings: array<string, array<string, mixed>>}>
     */
    public function groups(): array
    {
        return config('settings.groups', []);
    }

    /**
     * Get the definitions of a group's settings that may be stored at the scope.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitionsFor(string $group, SettingScope $scope): array
    {
        $settings = $this->groups()[$group]['settings'] ?? [];

        return array_filter(
            $settings,
            fn (array $definition): bool => in_array($scope->value, $definition['scopes'], true),
        );
    }

    /**
     * Get the definition of a single setting.
     *
     * @return array<string, mixed>
     */
    public function definition(string $key): array
    {
        foreach ($this->groups() as $group) {
            if (isset($group['settings'][$key])) {
                return $group['settings'][$key];
            }
        }

        throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    /**
     * Resolve a setting for an explicit school and user context.
     */
    public function get(string $key, ?int $schoolId = null, ?int $userId = null): mixed
    {
        $definition = $this->definition($key);

        $layers = [];

        if ($userId !== null && in_array(SettingScope::User->value, $definition['scopes'], true)) {
            $layers[] = $this->stored(SettingScope::User, $userId);
        }

        if ($schoolId !== null && in_array(SettingScope::School->value, $definition['scopes'], true)) {
            $layers[] = $this->stored(SettingScope::School, $schoolId);
        }

        $layers[] = $this->stored(SettingScope::Global, 0);

        foreach ($layers as $values) {
            if (array_key_exists($key, $values) && $values[$key] !== null) {
                return $this->cast($definition, $values[$key]);
            }
        }

        return $definition['default'] ?? null;
    }

    /**
     * Resolve a setting for the current request (authenticated user and active school).
     */
    public function current(string $key): mixed
    {
        $user = Auth::user();

        return $this->get($key, $this->currentSchool->id(), $user instanceof User ? $user->id : null);
    }

    /**
     * Resolve several settings for the current request.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function currentMany(array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $this->current($key)])->all();
    }

    /**
     * Get the effective value of a group's settings at a scope, with the inherited value
     * (what applies if this scope stores nothing) and whether this scope overrides it.
     *
     * @return array<string, array{value: mixed, inherited: mixed, overridden: bool}>
     */
    public function groupValues(string $group, SettingScope $scope, int $scopeId, ?int $schoolId = null): array
    {
        $stored = $this->stored($scope, $scopeId);
        $result = [];

        foreach ($this->definitionsFor($group, $scope) as $key => $definition) {
            $inherited = match ($scope) {
                SettingScope::Global => $definition['default'] ?? null,
                SettingScope::School => $this->get($key),
                SettingScope::User => $this->get($key, $schoolId),
            };

            $overridden = array_key_exists($key, $stored) && $stored[$key] !== null;

            $result[$key] = [
                'value' => $overridden ? $this->cast($definition, $stored[$key]) : $inherited,
                'inherited' => $inherited,
                'overridden' => $overridden,
            ];
        }

        return $result;
    }

    /**
     * Build the form fields for a group at a scope. Field names are the key without the group prefix.
     *
     * @return list<array{name: string, label: string, type: string, description: string|null, options: array<string, string>|null, value: mixed, inherited: mixed, overridden: bool}>
     */
    public function fieldsFor(string $group, SettingScope $scope, int $scopeId, ?int $schoolId = null): array
    {
        $definitions = $this->definitionsFor($group, $scope);
        $fields = [];

        foreach ($this->groupValues($group, $scope, $scopeId, $schoolId) as $key => $state) {
            $definition = $definitions[$key];

            $fields[] = [
                'name' => Str::after($key, "{$group}."),
                'label' => $definition['label'],
                'type' => $definition['type'],
                'description' => $definition['description'] ?? null,
                'options' => $definition['options'] ?? null,
                ...$state,
            ];
        }

        return $fields;
    }

    /**
     * Build validation rules for a group at a scope. Below the global scope every value
     * may be null, meaning "inherit".
     *
     * @return array<string, list<mixed>>
     */
    public function rulesFor(string $group, SettingScope $scope): array
    {
        $rules = [];

        foreach ($this->definitionsFor($group, $scope) as $key => $definition) {
            $fieldRules = $definition['rules'] ?? match ($definition['type']) {
                'boolean' => ['required', 'boolean'],
                'integer' => ['required', 'integer'],
                'select' => ['required', Rule::in(array_map('strval', array_keys($definition['options'] ?? [])))],
                default => ['nullable', 'string', 'max:255'],
            };

            if ($scope !== SettingScope::Global) {
                $fieldRules = ['nullable', ...array_filter($fieldRules, fn (mixed $rule): bool => ! in_array($rule, ['required', 'nullable'], true))];
            }

            $rules['values.'.Str::after($key, "{$group}.")] = array_values($fieldRules);
        }

        return ['values' => ['required', 'array'], ...$rules];
    }

    /**
     * Map short field names back to full setting keys.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function qualify(string $group, SettingScope $scope, array $values): array
    {
        $qualified = [];

        foreach (array_keys($this->definitionsFor($group, $scope)) as $key) {
            $name = Str::after($key, "{$group}.");

            if (array_key_exists($name, $values)) {
                $qualified[$key] = $values[$name];
            }
        }

        return $qualified;
    }

    /**
     * Store values at a scope. A null value removes the override so the inherited value applies.
     *
     * @param  array<string, mixed>  $values
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public function put(SettingScope $scope, int $scopeId, array $values): array
    {
        $stored = $this->stored($scope, $scopeId);
        $old = [];
        $new = [];

        foreach ($values as $key => $value) {
            $definition = $this->definition($key);

            if (! in_array($scope->value, $definition['scopes'], true)) {
                throw new InvalidArgumentException("Setting [{$key}] cannot be stored at the {$scope->value} scope.");
            }

            $value = $value === null ? null : $this->cast($definition, $value);
            $previous = $stored[$key] ?? null;

            if ($previous === $value) {
                continue;
            }

            if ($value === null) {
                Setting::query()->where(['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key])->delete();
            } else {
                Setting::query()->updateOrCreate(
                    ['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key],
                    ['value' => $value],
                );
            }

            $old[$key] = $previous;
            $new[$key] = $value;
        }

        $this->cache->forget($this->cacheKey($scope, $scopeId));

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Get the raw stored values of a scope.
     *
     * @return array<string, mixed>
     */
    private function stored(SettingScope $scope, int $scopeId): array
    {
        return $this->cache->remember(
            $this->cacheKey($scope, $scopeId),
            (int) config('settings.cache_ttl', 3600),
            fn (): array => Setting::query()
                ->where('scope', $scope)
                ->where('scope_id', $scopeId)
                ->pluck('value', 'key')
                ->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function cast(array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            default => (string) $value,
        };
    }

    private function cacheKey(SettingScope $scope, int $scopeId): string
    {
        return "settings:{$scope->value}:{$scopeId}";
    }
}
