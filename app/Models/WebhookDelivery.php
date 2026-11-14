<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event sent (or being sent) to one endpoint.
 *
 * @property int $id
 * @property string $uuid
 * @property int $school_id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $error
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['uuid', 'school_id', 'webhook_endpoint_id', 'event', 'payload'])]
class WebhookDelivery extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
