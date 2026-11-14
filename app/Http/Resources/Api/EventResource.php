<?php

namespace App\Http\Resources\Api;

use App\Models\SchoolEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolEvent
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'all_day' => $this->all_day,
            'audience' => $this->audience->value,
            'school_class' => $this->whenLoaded('schoolClass', fn (): ?string => $this->schoolClass?->name),
        ];
    }
}
