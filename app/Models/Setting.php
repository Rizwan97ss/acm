<?php

namespace App\Models;

use App\Enums\SettingScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Read and write settings through App\Services\Settings\SettingsService, never directly.
 *
 * @property int $id
 * @property SettingScope $scope
 * @property int $scope_id
 * @property string $key
 * @property mixed $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['scope', 'scope_id', 'key', 'value'])]
class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => SettingScope::class,
            'scope_id' => 'integer',
            'value' => 'json',
        ];
    }
}
