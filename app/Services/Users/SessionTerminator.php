<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ends a user's other ways of being signed in after a credential change: browser sessions
 * (database driver), "remember me" cookies and personal API tokens.
 */
class SessionTerminator
{
    /**
     * @return array{sessions: int, tokens: int}
     */
    public function signOutEverywhere(User $user, ?string $exceptSessionId = null): array
    {
        $sessions = 0;

        if (config('session.driver') === 'database') {
            $sessions = DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($exceptSessionId, fn ($query, string $sessionId) => $query->where('id', '!=', $sessionId))
                ->delete();
        }

        $user->setRememberToken(Str::random(60));
        $user->saveQuietly();

        $tokens = $user->tokens()->delete();

        Log::info('Signed user out of other sessions.', [
            'user_id' => $user->getKey(),
            'sessions' => $sessions,
            'tokens' => $tokens,
        ]);

        return ['sessions' => $sessions, 'tokens' => $tokens];
    }
}
