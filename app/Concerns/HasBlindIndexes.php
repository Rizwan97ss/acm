<?php

namespace App\Concerns;

use App\Services\Security\BlindIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Keeps blind-index columns in sync with encrypted attributes so they can be
 * searched by exact match. Models declare the mapping in `blindIndexes()`:
 *
 *     protected function blindIndexes(): array { return ['phone' => 'phone_index']; }
 *
 * and may override `normalizeForBlindIndex()` to canonicalize values before hashing.
 *
 * @mixin Model
 */
trait HasBlindIndexes
{
    /**
     * Map of encrypted attribute => blind index column.
     *
     * @return array<string, string>
     */
    abstract protected function blindIndexes(): array;

    public static function bootHasBlindIndexes(): void
    {
        static::saving(function (self $model): void {
            foreach ($model->blindIndexes() as $attribute => $column) {
                if ($model->isDirty($attribute)) {
                    $model->setAttribute($column, $model->blindIndexFor($attribute, $model->getAttribute($attribute)));
                }
            }
        });
    }

    /**
     * Compute the blind index of a value for one of the model's indexed attributes.
     */
    public function blindIndexFor(string $attribute, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = $this->normalizeForBlindIndex($attribute, (string) $value);

        if ($normalized === '') {
            return null;
        }

        return app(BlindIndex::class)->compute($normalized, $this->getTable().'.'.$attribute);
    }

    /**
     * Find rows whose encrypted attribute exactly matches the given plaintext.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereBlindIndex(Builder $query, string $attribute, string $value): Builder
    {
        $column = $this->blindIndexes()[$attribute]
            ?? throw new InvalidArgumentException("[{$attribute}] has no blind index.");

        return $query->where($column, $this->blindIndexFor($attribute, $value));
    }

    protected function normalizeForBlindIndex(string $attribute, string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
