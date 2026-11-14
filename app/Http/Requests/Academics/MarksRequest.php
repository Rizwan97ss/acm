<?php

namespace App\Http\Requests\Academics;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Subject-level access is checked in the controller once the section is resolved.
 */
class MarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('grades.create') || $this->user()->can('grades.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'integer', 'distinct'],
            'marks.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['required', 'boolean'],
            'marks.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, array{marks_obtained: float|null, is_absent: bool, remarks: string|null}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ((array) $this->validated('marks') as $row) {
            $rows[(int) $row['student_id']] = [
                'marks_obtained' => isset($row['marks_obtained']) && $row['marks_obtained'] !== '' ? (float) $row['marks_obtained'] : null,
                'is_absent' => filter_var($row['is_absent'], FILTER_VALIDATE_BOOL),
                'remarks' => isset($row['remarks']) && $row['remarks'] !== '' ? (string) $row['remarks'] : null,
            ];
        }

        return $rows;
    }
}
