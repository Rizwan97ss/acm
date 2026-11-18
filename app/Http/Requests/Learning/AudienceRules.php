<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Validation\Validator;

/**
 * Class, optional section and subject of the current school, consistent with each other.
 */
trait AudienceRules
{
    use ValidatesSchoolRecords;

    /**
     * @return array<string, mixed>
     */
    protected function audienceRules(): array
    {
        return [
            'school_class_id' => ['required', 'integer', $this->existsInSchool('school_classes')],
            'section_id' => ['nullable', 'integer', $this->existsInSchool('sections')],
            'subject_id' => ['required', 'integer', $this->existsInSchool('subjects')],
        ];
    }

    protected function checkAudience(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['school_class_id', 'section_id', 'subject_id'])) {
            return;
        }

        $classId = $this->integer('school_class_id');

        if ($this->filled('section_id') && ! Section::query()->whereKey($this->integer('section_id'))->where('school_class_id', $classId)->exists()) {
            $validator->errors()->add('section_id', __('Choose a section of the selected class.'));
        }

        $class = SchoolClass::query()->find($classId);

        if ($class !== null && ! $class->subjects()->whereKey($this->integer('subject_id'))->exists()) {
            $validator->errors()->add('subject_id', __('This subject is not taught in :class.', ['class' => $class->name]));
        }
    }
}
