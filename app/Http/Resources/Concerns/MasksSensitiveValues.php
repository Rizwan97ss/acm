<?php

namespace App\Http\Resources\Concerns;

trait MasksSensitiveValues
{
    /**
     * "•••• 4567": enough to recognise a value without disclosing it.
     */
    protected function mask(?string $value, int $visible = 4): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $compact = (string) preg_replace('/\s+/', '', $value);

        return '•••• '.mb_substr($compact, -min($visible, max(1, mb_strlen($compact) - 2)));
    }
}
