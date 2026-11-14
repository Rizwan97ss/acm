<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes the audit trail to the `audit_logs` table (shown in Administration → Audit logs)
 * and mirrors every entry to the `audit` log channel. Values arrive already masked by
 * callers such as the Auditable trait, so secrets reach neither destination.
 */
class AuditLogger
{
    public const string CHANNEL = 'audit';

    public function __construct(
        private CurrentSchool $currentSchool,
        private Request $request,
    ) {}

    /**
     * Record an audit entry.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        $schoolId = $auditable?->getAttribute('school_id')
            ?? $this->currentSchool->id()
            ?? $actor?->school_id;

        $entry = AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => app()->runningInConsole() ? null : $this->request->ip(),
            'user_agent' => app()->runningInConsole() ? null : Str::limit((string) $this->request->userAgent(), 500, ''),
        ]);

        Log::channel(self::CHANNEL)->info($event, [
            'audit_id' => $entry->id,
            'school_id' => $entry->school_id,
            'user_id' => $entry->user_id,
            'auditable_type' => $entry->auditable_type,
            'auditable_id' => $entry->auditable_id,
            'old_values' => $entry->old_values,
            'new_values' => $entry->new_values,
            'ip_address' => $entry->ip_address,
        ]);

        return $entry;
    }
}
