<?php

namespace App\Services\Security;

use Illuminate\Support\Str;

/**
 * Computes keyed, deterministic HMAC digests ("blind indexes") so encrypted
 * columns can still be matched exactly without ever decrypting them in SQL.
 */
class BlindIndex
{
    /**
     * Compute the blind index for a normalized value within a context (usually "table.column").
     */
    public function compute(string $value, string $context): string
    {
        return hash_hmac('sha256', $context.'|'.$value, $this->key());
    }

    private function key(): string
    {
        $configured = config('security.blind_index_key');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appKey = (string) config('app.key');

        if (Str::startsWith($appKey, 'base64:')) {
            $appKey = base64_decode(Str::after($appKey, 'base64:'));
        }

        return hash_hmac('sha256', 'blind-index-key-derivation', $appKey);
    }
}
