<?php

namespace App\Http\Requests\Academics;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Section access and the editable date window are checked in the controller,
 * because they depend on the resolved section and academic year.
 */
class AttendanceRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        return $this->user()->can('attendance.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', $this->existsInSchool('sections')],
            'date' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'integer', 'distinct'],
            'entries.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, array{status: string, remarks: string|null}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ((array) $this->validated('entries') as $entry) {
            $entries[(int) $entry['student_id']] = [
                'status' => (string) $entry['status'],
                'remarks' => isset($entry['remarks']) && $entry['remarks'] !== '' ? (string) $entry['remarks'] : null,
            ];
        }

        return $entries;
    }
}
