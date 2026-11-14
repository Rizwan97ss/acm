<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Rbac\PermissionResolver;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use App\Services\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentSchool::class);
        $this->app->scoped(Tenancy::class);
        $this->app->scoped(PermissionResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $settings = app(SettingsService::class);
            $minimum = (int) $settings->get('security.password_min_length');

            if (app()->isProduction()) {
                return Password::min(max(12, $minimum))
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised();
            }

            $rule = Password::min(max(8, $minimum));

            if ($settings->get('security.password_require_mixed_case')) {
                $rule->mixedCase();
            }

            if ($settings->get('security.password_require_numbers')) {
                $rule->numbers();
            }

            if ($settings->get('security.password_require_symbols')) {
                $rule->symbols();
            }

            return $rule;
        });
    }

    /**
     * Resolve "module.action" abilities against the user's RBAC permissions, so
     * `can:users.view` middleware, `@can` and `Gate::allows()` all work. Model
     * abilities (update, delete...) fall through to their policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
