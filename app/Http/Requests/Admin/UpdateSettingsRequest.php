<?php

namespace App\Http\Requests\Admin;

use App\Enums\SettingScope;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use App\Services\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a settings group against the rules declared in config/settings.php.
 * Shared by organisation settings (global or school scope, depending on the active
 * school) and personal preferences (user scope).
 */
class UpdateSettingsRequest extends FormRequest
{
    public function scope(): SettingScope
    {
        if ($this->routeIs('preferences.update')) {
            return SettingScope::User;
        }

        return app(CurrentSchool::class)->isGlobal() || app(Tenancy::class)->isSingle() ? SettingScope::Global : SettingScope::School;
    }

    /**
     * Personal preferences belong to every user; organisation settings need settings.edit.
     */
    public function authorize(): bool
    {
        return $this->scope() === SettingScope::User || $this->user()->can('settings.edit');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return app(SettingsService::class)->rulesFor((string) $this->route('group'), $this->scope());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->rules())
            ->keys()
            ->mapWithKeys(fn (string $key): array => [$key => str($key)->after('values.')->replace('_', ' ')->toString()])
            ->all();
    }
}
