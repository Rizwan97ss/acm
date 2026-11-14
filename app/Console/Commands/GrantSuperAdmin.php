<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Bootstraps the first platform administrator. Super admins are platform users,
 * so the account is detached from any school.
 */
#[Signature('rbac:grant-super-admin {email : Email address of an existing user}')]
#[Description('Grant the Super Admin role to an existing user (use once to bootstrap a new installation)')]
class GrantSuperAdmin extends Command
{
    public function handle(PermissionResolver $resolver, AuditLogger $audit): int
    {
        $user = User::query()->where('email', Str::lower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->components->error('No user with that email address. Register or create the account first.');

            return self::FAILURE;
        }

        $role = Role::query()->whereNull('school_id')->where('slug', config('rbac.super_admin_role'))->first();

        if ($role === null) {
            Artisan::call('rbac:sync');
            $role = Role::query()->whereNull('school_id')->where('slug', config('rbac.super_admin_role'))->firstOrFail();
        }

        $user->forceFill(['school_id' => null, 'is_active' => true])->save();
        $user->roles()->syncWithoutDetaching([$role->id => ['created_at' => now()]]);
        $resolver->flush();

        $audit->log('user.super_admin_granted', $user, [], ['via' => 'console']);

        $this->components->info("{$user->email} is now a Super Admin.");

        return self::SUCCESS;
    }
}
