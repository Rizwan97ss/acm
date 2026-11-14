<?php

namespace App\Services\People;

use App\Models\AdmissionApplication;
use App\Models\Employee;
use App\Models\School;
use App\Models\Student;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Generates sequential, human-readable reference numbers per school:
 *
 *   student      GHS260001   (school code + 2-digit year + 4-digit sequence)
 *   employee     GHS-E0001
 *   application  APP-2026-00001
 *
 * The number is reserved under an atomic lock while the record is created, so
 * concurrent requests never receive the same number; the unique index is the backstop.
 */
class ReferenceNumberGenerator
{
    private const int LOCK_SECONDS = 10;

    /**
     * Run the callback with the next free number while holding the lock.
     *
     * @template TReturn
     *
     * @param  'student'|'employee'|'application'  $kind
     * @param  Closure(string): TReturn  $create
     * @return TReturn
     */
    public function withNext(string $kind, int $schoolId, Closure $create): mixed
    {
        return Cache::lock("reference-number:{$kind}:{$schoolId}", self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, fn () => $create($this->next($kind, $schoolId)));
    }

    /**
     * @param  'student'|'employee'|'application'  $kind
     */
    private function next(string $kind, int $schoolId): string
    {
        [$model, $column, $prefix, $digits] = match ($kind) {
            'student' => [Student::class, 'admission_no', $this->schoolCode($schoolId).now()->format('y'), 4],
            'employee' => [Employee::class, 'employee_no', $this->schoolCode($schoolId).'-E', 4],
            'application' => [AdmissionApplication::class, 'application_no', 'APP-'.now()->format('Y').'-', 5],
        };

        /** @var class-string<Model> $model */
        $last = $model::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where($column, 'like', addcslashes($prefix, '%_\\').'%')
            ->orderByDesc($column)
            ->value($column);

        $sequence = is_string($last) ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
    }

    private function schoolCode(int $schoolId): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', (string) School::query()->whereKey($schoolId)->value('code'));
    }
}
