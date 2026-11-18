<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('rbac:sync {--reset-roles : Reset system role permissions to the defaults in config/rbac.php}')]
#[Description('Sync declared permissions and system roles from config/rbac.php')]
class SyncPermissions extends Command
{
    public function handle(PermissionRegistry $registry, PermissionResolver $resolver): int
    {
        DB::transaction(function () use ($registry): void {
            $added = $this->syncPermissions($registry);
            $this->syncSystemRoles($registry, $added);
        });

        $resolver->flush();

        $this->components->info(sprintf('Synced %d permissions and %d system roles.', count($registry->all()), count(config('rbac.roles', []))));

        return self::SUCCESS;
    }

    /**
     * @return list<string> permissions created by this run (new modules or actions)
     */
    private function syncPermissions(PermissionRegistry $registry): array
    {
        $added = [];

        foreach ($registry->modules() as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $permission = Permission::query()->updateOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'module' => $module,
                        'action' => $action,
                        'description' => Str::ucfirst($action).' '.Str::lower($definition['label']),
                    ],
                );

                if ($permission->wasRecentlyCreated) {
                    $added[] = $permission->name;
                }
            }
        }

        $stale = Permission::query()->whereNotIn('name', $registry->all());

        if ($stale->exists()) {
            $this->components->warn('Removing permissions no longer declared: '.$stale->pluck('name')->implode(', '));
            $stale->delete();
        }

        return $added;
    }

    /**
     * Create missing system roles. Existing roles keep their permissions unless --reset-roles is given,
     * so administrators' changes survive deployments; permissions introduced by this run (a new
     * module or action) are added to the system roles whose defaults include them.
     *
     * @param  list<string>  $added
     */
    private function syncSystemRoles(PermissionRegistry $registry, array $added): void
    {
        $permissionIds = Permission::query()->pluck('id', 'name');

        foreach (config('rbac.roles', []) as $slug => $definition) {
            $role = Role::query()->firstOrNew(['school_id' => null, 'slug' => $slug]);
            $isNew = ! $role->exists;

            $role->fill([
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'is_system' => true,
            ])->save();

            $names = $slug === config('rbac.super_admin_role')
                ? []
                : $registry->expand($definition['permissions'], $definition['except'] ?? []);

            if ($isNew || $this->option('reset-roles')) {
                $role->permissions()->sync($permissionIds->only($names)->values());
            } elseif ($added !== []) {
                $role->permissions()->syncWithoutDetaching($permissionIds->only(array_values(array_intersect($names, $added)))->values());
            }
        }
    }
}
