<?php

namespace App\Http\Resources\Api;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student summary for API clients. Encrypted personal fields (national ID, medical
 * notes, address) are never exposed over the API.
 *
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->relationLoaded('currentEnrollment') ? $this->currentEnrollment : null;

        return [
            'id' => $this->id,
            'admission_no' => $this->admission_no,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'admission_date' => $this->admission_date->toDateString(),
            'status' => $this->status->value,
            'current_enrollment' => $enrollment === null ? null : [
                'academic_year_id' => $enrollment->academic_year_id,
                'section_id' => $enrollment->section_id,
                'section' => $enrollment->relationLoaded('section') ? $enrollment->section->fullName() : null,
                'roll_number' => $enrollment->roll_number,
            ],
        ];
    }
}
