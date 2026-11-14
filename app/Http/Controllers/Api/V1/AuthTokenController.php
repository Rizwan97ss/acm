<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Sign-in for mobile apps: exchanges credentials (plus a two-factor code when the
 * account uses 2FA) for a bearer token carrying all API scopes.
 */
class AuthTokenController extends Controller
{
    private const int TOKEN_DAYS = 30;

    public function __construct(
        private AuditLogger $audit,
        private SettingsService $settings,
        private TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            $this->refuse($user, $validated['email'], 'invalid_credentials', 'email', __('auth.failed'));
        }

        $this->ensureAccountCanSignIn($user);
        $this->ensureSecondFactor($user, $validated['code'] ?? null);

        $token = $user->createToken($validated['device_name'], ['*'], now()->addDays(self::TOKEN_DAYS));

        $this->audit->log('api.token_issued', $token->accessToken, [], ['name' => $validated['device_name'], 'source' => 'mobile_sign_in'], $user);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Signs the current device out by revoking the token used for this request.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function ensureAccountCanSignIn(User $user): void
    {
        $school = $user->school_id !== null ? $user->school : null;

        if (! $user->is_active || ($user->school_id !== null && ($school === null || ! $school->is_active))) {
            $this->refuse($user, $user->email, 'account_inactive', 'email', __('This account has been deactivated. Contact your administrator.'));
        }

        if (! $user->hasVerifiedEmail()) {
            $this->refuse($user, $user->email, 'email_unverified', 'email', __('Verify your email address on the website first.'));
        }
    }

    private function ensureSecondFactor(User $user, ?string $code): void
    {
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at === null) {
            if ($this->settings->get('security.require_two_factor')) {
                $this->refuse($user, $user->email, 'two_factor_not_enrolled', 'email', __('Set up two-factor authentication on the website first.'));
            }

            return;
        }

        if ($code === null || $code === '') {
            throw ValidationException::withMessages(['code' => __('Enter the code from your authenticator app or a recovery code.')]);
        }

        if ($this->twoFactor->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code)) {
            return;
        }

        $recoveryCode = collect($user->recoveryCodes())->first(fn (string $recovery): bool => hash_equals($recovery, $code));

        if ($recoveryCode === null) {
            $this->refuse($user, $user->email, 'invalid_two_factor_code', 'code', __('The two-factor code is invalid.'));
        }

        $user->replaceRecoveryCode($recoveryCode);
    }

    /**
     * Records the refused sign-in in the audit trail (and so the audit log channel),
     * then rejects the request. Passwords and codes are never recorded.
     */
    private function refuse(?User $user, string $email, string $reason, string $field, string $message): never
    {
        $this->audit->log('api.sign_in_failed', $user, [], ['email' => $email, 'reason' => $reason], $user);

        throw ValidationException::withMessages([$field => $message]);
    }
}
