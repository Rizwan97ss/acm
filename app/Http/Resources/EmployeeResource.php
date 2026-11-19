<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MasksSensitiveValues;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bank details and national IDs are only decrypted for users who may edit the record.
 *
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
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
            'photo_url' => $this->photo_path !== null ? route('people.photos.show', ['kind' => 'staff', 'id' => $this->id, 'v' => substr(md5($this->photo_path), 0, 8)]) : null,
            'employee_no' => $this->employee_no,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name', 'email'])),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender?->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'email' => $this->email,
            'phone' => $canUpdate ? $this->phone : $this->mask($this->phone),
            'national_id' => $canUpdate ? $this->national_id : $this->mask($this->national_id),
            'bank_account' => $canUpdate ? $this->bank_account : $this->mask($this->bank_account),
            'address' => $canUpdate ? $this->address : null,
            'is_teaching' => $this->is_teaching,
            'designation' => $this->designation,
            'department' => $this->department,
            'qualification' => $this->qualification,
            'employment_type' => $this->employment_type->value,
            'employment_type_label' => $this->employment_type->label(),
            'joined_on' => $this->joined_on->toDateString(),
            'left_on' => $this->left_on?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'can' => [
                'update' => $canUpdate,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
