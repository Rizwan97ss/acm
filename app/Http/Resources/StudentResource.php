<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MasksSensitiveValues;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sensitive fields (national ID, medical notes, address) are decrypted only for users
 * who may edit the student; others get masked values or a presence flag.
 *
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    use MasksSensitiveValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->resource) ?? false;

        return [
            'id' => $this->id,
            'photo_url' => $this->photo_path !== null ? route('people.photos.show', ['kind' => 'students', 'id' => $this->id, 'v' => substr(md5($this->photo_path), 0, 8)]) : null,
            'admission_no' => $this->admission_no,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'email' => $this->email,
            'blood_group' => $this->blood_group,
            'national_id' => $canUpdate ? $this->national_id : $this->mask($this->national_id),
            'medical_notes' => $canUpdate ? $this->medical_notes : null,
            'has_medical_notes' => filled($this->medical_notes),
            'address' => $canUpdate ? $this->address : null,
            'admission_date' => $this->admission_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'has_portal_account' => $this->user_id !== null,
            'current_enrollment' => $this->whenLoaded('currentEnrollment', fn () => $this->enrollmentData($this->currentEnrollment)),
            'can' => [
                'update' => $canUpdate,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'invite' => $request->user()?->can('invite', $this->resource) ?? false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enrollmentData(?Enrollment $enrollment): ?array
    {
        if ($enrollment === null) {
            return null;
        }

        return [
            'id' => $enrollment->id,
            'section_id' => $enrollment->section_id,
            'section' => $enrollment->relationLoaded('section') ? $enrollment->section->fullName() : null,
            'roll_number' => $enrollment->roll_number,
        ];
    }
}
