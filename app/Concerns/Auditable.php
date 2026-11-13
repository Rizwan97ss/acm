<?php

namespace App\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records created, updated and deleted events for the model. Hidden attributes are
 * redacted and encrypted attributes are masked so secrets never reach the audit trail.
 *
 * Models may declare `protected array $auditExclude = [...]` to skip noisy attributes.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->writeAudit('created', [], $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $changes = $model->auditableValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = $model->auditableValues(array_intersect_key($model->getRawOriginal(), $changes));

            $model->writeAudit('updated', $original, $changes);
        });

        static::deleted(function (self $model): void {
            $model->writeAudit('deleted', $model->auditableValues($model->getAttributes()), []);
        });
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAudit(string $action, array $old, array $new): void
    {
        $event = Str::snake(class_basename($this)).'.'.$action;

        app(AuditLogger::class)->log($event, $this, $old, $new);
    }

    /**
     * Strip excluded attributes and mask sensitive ones.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableValues(array $attributes): array
    {
        $excluded = [...($this->auditExclude ?? []), 'created_at', 'updated_at', 'remember_token'];
        $values = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            if (in_array($key, $this->getHidden(), true)) {
                $values[$key] = $value === null ? null : '[redacted]';
            } elseif ($this->hasCast($key, ['encrypted', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object'])) {
                $values[$key] = $value === null ? null : '[encrypted]';
            } else {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
