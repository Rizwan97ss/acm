<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'school_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn (array $attributes) => ['school_id' => $school->id]);
    }

    /**
     * Grant the given permissions, creating permission rows as needed.
     *
     * @param  list<string>  $names
     */
    public function withPermissions(array $names): static
    {
        return $this->afterCreating(function (Role $role) use ($names): void {
            $ids = collect($names)->map(function (string $name): int {
                [$module, $action] = explode('.', $name, 2);

                return Permission::query()->firstOrCreate(['name' => $name], ['module' => $module, 'action' => $action])->id;
            });

            $role->permissions()->syncWithoutDetaching($ids);
        });
    }
}
