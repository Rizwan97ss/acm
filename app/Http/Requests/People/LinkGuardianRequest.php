<?php

namespace App\Http\Requests\People;

use App\Enums\GuardianRelationship;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Link an existing guardian (guardian_id) or create one inline (guardian.*) for a student.
 */
class LinkGuardianRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $student = $this->route('student');

        if (! $student instanceof Student || ! $this->user()->can('update', $student)) {
            return false;
        }

        return $this->filled('guardian_id')
            ? $this->user()->can('guardians.view')
            : $this->user()->can('create', Guardian::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'guardian_id' => ['nullable', 'integer', $this->existsInSchool('guardians')],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['required', 'boolean'],
            'is_emergency_contact' => ['required', 'boolean'],
        ];

        if ($this->filled('guardian_id')) {
            return $rules;
        }

        return [...$rules, 'guardian' => ['required', 'array'], ...GuardianRequest::guardianRules('guardian.')];
    }
}
