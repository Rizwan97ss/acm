<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A URL that receives signed event notifications. The signing secret is encrypted at
 * rest and shown to the administrator only once.
 *
 * @property int $id
 * @property int $school_id
 * @property string $url
 * @property string|null $description
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'url', 'description', 'secret', 'events', 'is_active', 'created_by'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'consecutive_failures' => 'integer',
        ];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
