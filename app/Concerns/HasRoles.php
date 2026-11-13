<?php

namespace App\Concerns;

use App\Models\Role;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin Model
 */
trait HasRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('created_at');
    }

    public function hasPermission(string $permission): bool
    {
        return app(PermissionResolver::class)->has($this, $permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return app(PermissionResolver::class)->isSuperAdmin($this);
    }

    /**
     * Platform users are not bound to a school and may operate across schools.
     */
    public function isPlatformUser(): bool
    {
        return $this->school_id === null;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return app(PermissionResolver::class)->permissionsFor($this);
    }
}
