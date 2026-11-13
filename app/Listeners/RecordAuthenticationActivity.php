<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;

/**
 * Writes authentication activity to the audit trail and tracks last login details.
 */
class RecordAuthenticationActivity
{
    public function __construct(
        private AuditLogger $audit,
        private Request $request,
    ) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->saveQuietly();

        $this->audit->log('auth.login', $event->user, [], ['remember' => $event->remember], $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.logout', $event->user, actor: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->log('auth.failed', $user, [], ['email' => $event->credentials['email'] ?? null], $user);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->audit->log('auth.lockout', null, [], ['email' => $event->request->input('email')]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.password_reset', $event->user, actor: $event->user);
        }
    }
}
