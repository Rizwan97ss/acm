<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SettingScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal (user-scope) overrides of school and platform settings.
 */
class PreferencesController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private CurrentSchool $currentSchool,
    ) {}

    public function edit(Request $request): Response
    {
        $groups = collect($this->settings->groups())
            ->filter(fn (array $definition, string $key): bool => $this->settings->definitionsFor($key, SettingScope::User) !== [])
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'] ?? null,
                'fields' => $this->settings->fieldsFor($key, SettingScope::User, $request->user()->id, $this->currentSchool->id()),
            ])
            ->values();

        return Inertia::render('settings/preferences', [
            'groups' => $groups,
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        abort_if($this->settings->definitionsFor($group, SettingScope::User) === [], 404);

        $this->settings->put(
            SettingScope::User,
            $request->user()->id,
            $this->settings->qualify($group, SettingScope::User, $request->validated('values')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferences saved.')]);

        return to_route('preferences.edit');
    }
}
