<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Sensitive fields (phone) are decrypted only for actors allowed to edit the user;
     * everyone else receives a masked value.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->resource) ?? false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $canUpdate ? $this->phone : $this->maskedPhone(),
            'is_active' => $this->is_active,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'school' => $this->whenLoaded('school', fn () => $this->school ? ['id' => $this->school->id, 'name' => $this->school->name] : null),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values()),
            'can' => [
                'update' => $canUpdate,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }

    private function maskedPhone(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->phone);

        return '•••• '.substr((string) $digits, -2);
    }
}
