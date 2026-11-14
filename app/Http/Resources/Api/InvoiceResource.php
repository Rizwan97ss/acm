<?php

namespace App\Http\Resources\Api;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are integer minor units (cents).
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn (): array => [
                'id' => $this->student->id,
                'name' => $this->student->full_name,
                'admission_no' => $this->student->admission_no,
            ]),
            'issued_on' => $this->issued_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'total' => $this->total,
            'paid' => $this->paid,
            'balance' => $this->balance(),
            'status' => $this->status->value,
            'overdue' => $this->isOverdue(),
        ];
    }
}
