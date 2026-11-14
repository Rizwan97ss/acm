<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MasksSensitiveValues;
use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdmissionApplication
 */
class AdmissionApplicationResource extends JsonResource
{
    use MasksSensitiveValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeeContact = $user !== null && ($user->can('admissions.edit') || $user->can('admissions.approve'));

        return [
            'id' => $this->id,
            'application_no' => $this->application_no,
            'academic_year_id' => $this->academic_year_id,
            'school_class_id' => $this->school_class_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => $this->academicYear->name),
            'school_class' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass->name),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'previous_school' => $this->previous_school,
            'guardian_name' => $this->guardian_name,
            'guardian_relationship' => $this->guardian_relationship->value,
            'guardian_relationship_label' => $this->guardian_relationship->label(),
            'guardian_phone' => $canSeeContact ? $this->guardian_phone : $this->mask($this->guardian_phone),
            'guardian_email' => $this->guardian_email,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->only(['id', 'name'])),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'decision_notes' => $this->decision_notes,
            'student_id' => $this->student_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'can' => [
                'update' => $user?->can('update', $this->resource) ?? false,
                'decide' => $user?->can('decide', $this->resource) ?? false,
                'enroll' => $user?->can('enroll', $this->resource) ?? false,
            ],
        ];
    }
}
