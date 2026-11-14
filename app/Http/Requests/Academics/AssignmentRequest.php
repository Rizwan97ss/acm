<?php

namespace App\Http\Requests\Academics;

use App\Enums\AssignmentType;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Assignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment
            ? $this->user()->can('update', $assignment)
            : $this->user()->can('create', Assignment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', $this->existsInSchool('sections')],
            'subject_id' => ['required', 'integer', $this->existsInSchool('subjects')],
            'type' => ['required', Rule::enum(AssignmentType::class)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'due_on' => ['required', 'date'],
            'max_marks' => ['nullable', 'numeric', 'min:1', 'max:1000'],
        ];
    }
}
