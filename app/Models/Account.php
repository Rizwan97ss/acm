<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use App\Enums\AccountType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A general-ledger account. System accounts are created by LedgerService.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property bool $is_system
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'code', 'name', 'type', 'is_system'])]
class Account extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
