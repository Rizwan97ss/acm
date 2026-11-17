<?php

namespace App\Http\Controllers;

use App\Enums\WebhookEvent;
use App\Models\ContactInquiry;
use App\Services\Import\ImportRegistry;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public product landing page. Every figure shown is derived from the running
 * application itself, never typed in, so the page cannot overstate what exists.
 */
class WelcomeController extends Controller
{
    public function __invoke(PermissionRegistry $permissions): Response
    {
        return Inertia::render('welcome', [
            'facts' => [
                'modules' => count((array) config('rbac.modules')),
                'roles' => count((array) config('rbac.roles')),
                'permissions' => count($permissions->all()),
                'apiEndpoints' => $this->apiEndpointCount(),
                'importers' => count(ImportRegistry::IMPORTERS),
                'webhookEvents' => count(WebhookEvent::cases()) - 1,
            ],
            'inquiryOptions' => [
                'interests' => self::options(ContactInquiry::INTERESTS),
                'schoolSizes' => self::options(ContactInquiry::SCHOOL_SIZES),
            ],
        ]);
    }

    private function apiEndpointCount(): int
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
            ->count();
    }

    /**
     * @param  array<string, string>  $options
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $options): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys($options),
            array_values($options),
        );
    }
}
