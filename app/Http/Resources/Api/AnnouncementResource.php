<?php

namespace App\Http\Resources\Api;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience->value,
            'school_class' => $this->whenLoaded('schoolClass', fn (): ?string => $this->schoolClass?->name),
            'is_pinned' => $this->is_pinned,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_on' => $this->expires_on?->toDateString(),
        ];
    }
}
