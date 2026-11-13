<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private CurrentSchool $currentSchool,
        private SettingsService $settings,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $school = $this->currentSchool->get();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => fn (): array => $user?->permissionNames() ?? [],
                'roles' => fn (): array => $user?->roles()->pluck('name')->all() ?? [],
                'isSuperAdmin' => fn (): bool => $user?->isSuperAdmin() ?? false,
            ],
            'tenant' => fn (): array => [
                'current' => $school ? [
                    'id' => $school->id,
                    'name' => $school->name,
                    'code' => $school->code,
                    'logo_url' => $school->logo_url,
                ] : null,
                'canSwitch' => $user !== null && $user->isPlatformUser() && $user->hasPermission('schools.view'),
                'schools' => $user !== null && $user->isPlatformUser() && $user->hasPermission('schools.view')
                    ? School::query()->active()->orderBy('name')->get(['id', 'name', 'code'])
                    : [],
            ],
            'branding' => fn (): array => [
                'name' => $this->settings->current('branding.app_name') ?: ($school->name ?? config('app.name')),
                'primaryColor' => $this->settings->current('branding.primary_color'),
                'logoUrl' => $school?->logo_url,
            ],
            'preferences' => fn (): array => $user === null ? [] : [
                'timezone' => $this->settings->current('localization.timezone'),
                'dateFormat' => $this->settings->current('localization.date_format'),
                'timeFormat' => $this->settings->current('localization.time_format'),
                'currency' => $this->settings->current('localization.currency'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
