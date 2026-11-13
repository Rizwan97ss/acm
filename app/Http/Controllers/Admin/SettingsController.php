<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organisation settings. Platform users in the all-schools context edit global values;
 * everyone else edits overrides for the active school.
 */
class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(): RedirectResponse
    {
        Gate::authorize('settings.view');

        $first = array_key_first($this->availableGroups());

        abort_if($first === null, 404);

        return to_route('admin.settings.edit', $first);
    }

    public function edit(string $group): Response
    {
        Gate::authorize('settings.view');

        $groups = $this->availableGroups();

        abort_unless(isset($groups[$group]), 404);

        return Inertia::render('admin/settings/edit', [
            'groups' => collect($groups)->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => $definition['label'],
            ])->values(),
            'group' => [
                'key' => $group,
                'label' => $groups[$group]['label'],
                'description' => $groups[$group]['description'] ?? null,
            ],
            'scope' => $this->scope()->value,
            'scopeLabel' => $this->currentSchool->get()->name ?? __('All schools (platform defaults)'),
            'fields' => $this->settings->fieldsFor($group, $this->scope(), $this->currentSchool->id() ?? 0),
            'canEdit' => Gate::allows('settings.edit'),
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group, AuditLogger $audit): RedirectResponse
    {
        abort_unless(isset($this->availableGroups()[$group]), 404);

        $scope = $request->scope();
        $changes = $this->settings->put(
            $scope,
            $this->currentSchool->id() ?? 0,
            $this->settings->qualify($group, $scope, $request->validated('values')),
        );

        if ($changes['new'] !== [] || $changes['old'] !== []) {
            $audit->log("settings.{$scope->value}.updated", $this->currentSchool->get(), $changes['old'], $changes['new']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('admin.settings.edit', $group);
    }

    private function scope(): SettingScope
    {
        return $this->currentSchool->isGlobal() ? SettingScope::Global : SettingScope::School;
    }

    /**
     * Groups that have at least one setting storable at the current scope.
     *
     * @return array<string, array<string, mixed>>
     */
    private function availableGroups(): array
    {
        return array_filter(
            $this->settings->groups(),
            fn (array $definition, string $key): bool => $this->settings->definitionsFor($key, $this->scope()) !== [],
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
