<?php

namespace App\Http\Resources;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin School
 */
class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'logo_url' => $this->logo_url,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent?->only(['id', 'name'])),
            'users_count' => $this->whenCounted('users'),
            'branches_count' => $this->whenCounted('branches'),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'switch' => $request->user()?->can('switch', $this->resource) ?? false,
            ],
        ];
    }
}
