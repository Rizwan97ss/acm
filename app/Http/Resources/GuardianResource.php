<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MasksSensitiveValues;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Guardian
 */
class GuardianResource extends JsonResource
{
    use MasksSensitiveValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->resource) ?? false;
        $pivot = $this->getRelationValue('pivot');

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $canUpdate ? $this->phone : $this->mask($this->phone),
            'occupation' => $this->occupation,
            'address' => $canUpdate ? $this->address : null,
            'has_portal_account' => $this->user_id !== null,
            'students_count' => $this->whenCounted('students'),
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn (Student $student): array => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'admission_no' => $student->admission_no,
                'relationship' => $student->getRelationValue('pivot')?->getAttribute('relationship'),
            ])->values()),
            'link' => $pivot ? [
                'relationship' => $pivot->getAttribute('relationship'),
                'is_primary' => (bool) $pivot->getAttribute('is_primary'),
                'is_emergency_contact' => (bool) $pivot->getAttribute('is_emergency_contact'),
            ] : null,
            'can' => [
                'update' => $canUpdate,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'invite' => $request->user()?->can('invite', $this->resource) ?? false,
            ],
        ];
    }
}
