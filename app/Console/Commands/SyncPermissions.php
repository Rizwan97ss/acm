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
            $this->syncPermissions($registry);
            $this->syncSystemRoles($registry);
        });

        $resolver->flush();

        $this->components->info(sprintf('Synced %d permissions and %d system roles.', count($registry->all()), count(config('rbac.roles', []))));

        return self::SUCCESS;
    }

    private function syncPermissions(PermissionRegistry $registry): void
    {
        foreach ($registry->modules() as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                Permission::query()->updateOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'module' => $module,
                        'action' => $action,
                        'description' => Str::ucfirst($action).' '.Str::lower($definition['label']),
                    ],
                );
            }
        }

        $stale = Permission::query()->whereNotIn('name', $registry->all());

        if ($stale->exists()) {
            $this->components->warn('Removing permissions no longer declared: '.$stale->pluck('name')->implode(', '));
            $stale->delete();
        }
    }

    /**
     * Create missing system roles. Existing roles keep their permissions unless --reset-roles is given,
     * so administrators' changes survive deployments.
     */
    private function syncSystemRoles(PermissionRegistry $registry): void
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

            if ($isNew || $this->option('reset-roles')) {
                $names = $slug === config('rbac.super_admin_role')
                    ? []
                    : $registry->expand($definition['permissions'], $definition['except'] ?? []);

                $role->permissions()->sync($permissionIds->only($names)->values());
            }
        }
    }
}
