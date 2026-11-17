<?php

namespace App\Services\Import;

use App\Models\User;
use Closure;
use Illuminate\Support\Str;

/**
 * State shared by every row of one import: who is importing, into which school, which
 * unique values earlier rows already used, and cached lookups (classes, sections …).
 */
final class ImportBatch
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $seen = [];

    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * Spreadsheet line of the row being checked or imported (the header is line 1).
     */
    public int $line = 0;

    public function __construct(
        public readonly User $actor,
        public readonly int $schoolId,
    ) {}

    /**
     * Record a value that must be unique within the file. Returns the earlier line number
     * when the value was already used, or null the first time.
     */
    public function duplicateOf(string $group, ?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = Str::lower(trim($value));

        if (isset($this->seen[$group][$normalized]) && $this->seen[$group][$normalized] !== $this->line) {
            return $this->seen[$group][$normalized];
        }

        $this->seen[$group][$normalized] = $this->line;

        return null;
    }

    /**
     * Forget the uniqueness bookkeeping so the same file can be validated again before saving.
     */
    public function resetSeen(): void
    {
        $this->seen = [];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function remember(string $key, Closure $resolve): mixed
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $resolve();
        }

        return $this->cache[$key];
    }
}
