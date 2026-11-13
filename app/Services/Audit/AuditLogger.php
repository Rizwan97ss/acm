<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
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

        return AuditLog::create([
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
    }
}
