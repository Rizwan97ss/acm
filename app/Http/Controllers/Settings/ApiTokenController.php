<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ApiAbility;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal API tokens. A token acts as its owner, limited to the scopes picked here;
 * the plain-text value is shown once.
 */
class ApiTokenController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/api-tokens', [
            'tokens' => $request->user()->tokens()->latest('id')->get()->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->values(),
            'abilities' => ApiAbility::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'distinct', Rule::in(ApiAbility::values())],
            'expires_in_days' => ['nullable', 'integer', Rule::in([30, 90, 365])],
        ]);

        $expiresInDays = $validated['expires_in_days'] ?? null;
        $token = $request->user()->createToken(
            $validated['name'],
            array_values($validated['abilities']),
            $expiresInDays === null ? null : now()->addDays($expiresInDays),
        );

        $this->audit->log('api.token_issued', $token->accessToken, [], ['name' => $validated['name'], 'abilities' => $validated['abilities']]);
        Inertia::flash('token', ['value' => $token->plainTextToken]);

        return to_route('api-tokens.index');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $record = $request->user()->tokens()->findOrFail($token);

        $this->audit->log('api.token_revoked', $record, ['name' => $record->name]);
        $record->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return to_route('api-tokens.index');
    }
}
