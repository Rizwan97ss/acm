<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\SessionTerminator;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private SessionTerminator $sessions,
        private AuditLogger $audit,
    ) {}

    /**
     * Validate and reset the user's forgotten password, then sign out every existing
     * session and API token in case the old password was compromised.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        $ended = $this->sessions->signOutEverywhere($user);

        $this->audit->log('auth.password_reset', $user, [], $ended, $user);
    }
}
