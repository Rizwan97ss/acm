<?php

namespace App\Http\Resources;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolClass
 */
class SchoolClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'description' => $this->description,
            'sections' => $this->whenLoaded('sections', fn () => $this->sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
                'room' => $section->room,
                'class_teacher_id' => $section->class_teacher_id,
                'class_teacher' => $section->relationLoaded('classTeacher') ? $section->classTeacher?->only(['id', 'name']) : null,
            ])->values()),
            'subjects' => $this->whenLoaded('subjects', fn () => $this->subjects->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'color' => $subject->color,
                'periods_per_week' => (int) $subject->getRelationValue('pivot')?->getAttribute('periods_per_week'),
            ])->values()),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
