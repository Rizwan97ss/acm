<?php

namespace App\Services\People;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates portal logins for students and guardians. No password is ever chosen by
 * staff: the person receives a password-reset link and sets their own.
 */
class PortalAccountService
{
    public function __construct(
        private PermissionResolver $resolver,
        private AuditLogger $audit,
    ) {}

    public function invite(User $actor, Student|Guardian $person): User
    {
        if ($person->user_id !== null) {
            throw ValidationException::withMessages(['account' => __('This person already has a portal account.')]);
        }

        if (blank($person->email)) {
            throw ValidationException::withMessages(['account' => __('Add an email address before inviting this person.')]);
        }

        $email = Str::lower((string) $person->email);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['account' => __('An account with this email address already exists.')]);
        }

        $role = Role::query()
            ->whereNull('school_id')
            ->where('slug', $person instanceof Student ? 'student' : 'parent')
            ->first() ?? throw ValidationException::withMessages(['account' => __('The portal role is missing. Run `php artisan rbac:sync`.')]);

        if ($this->resolver->missing($actor, $this->resolver->permissionsForRole($role)) !== []) {
            throw ValidationException::withMessages(['account' => __('You cannot grant the :role role.', ['role' => $role->name])]);
        }

        $user = DB::transaction(function () use ($person, $email, $role): User {
            $user = new User([
                'name' => $person->full_name,
                'email' => $email,
                'password' => Str::password(40),
                'school_id' => $person->school_id,
                'is_active' => true,
            ]);
            $user->email_verified_at = now();
            $user->save();

            $user->roles()->attach($role->id, ['created_at' => now()]);
            $person->forceFill(['user_id' => $user->id])->save();

            return $user;
        });

        $this->resolver->flush();

        Password::broker()->sendResetLink(['email' => $email]);

        $this->audit->log('portal_account.invited', $person, [], ['user_id' => $user->id, 'role' => $role->slug], $actor);

        return $user;
    }
}
