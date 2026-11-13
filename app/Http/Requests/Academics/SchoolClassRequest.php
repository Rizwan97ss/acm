<?php

namespace App\Http\Requests\Academics;

use App\Models\SchoolClass;
use App\Services\Academics\TeacherDirectory;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->schoolClass();

        return $class === null
            ? $this->user()->can('create', SchoolClass::class)
            : $this->user()->can('update', $class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = (int) app(CurrentSchool::class)->id();
        $class = $this->schoolClass();
        $teacherIds = app(TeacherDirectory::class)->query($schoolId)->pluck('id')->all();

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('school_classes')->where('school_id', $schoolId)->ignore($class?->id)],
            'level' => ['required', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sections' => ['present', 'array', 'max:26'],
            'sections.*.id' => ['nullable', 'integer', Rule::exists('sections', 'id')->where('school_class_id', $class->id ?? 0)],
            'sections.*.name' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'sections.*.capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sections.*.room' => ['nullable', 'string', 'max:30'],
            'sections.*.class_teacher_id' => ['nullable', 'integer', Rule::in($teacherIds)],
            'subjects' => ['present', 'array'],
            'subjects.*.id' => ['required', 'integer', 'distinct', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'subjects.*.periods_per_week' => ['required', 'integer', 'min:0', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.*.class_teacher_id.in' => __('The class teacher must be an active teacher of this school.'),
            'sections.*.name.distinct' => __('Section names must be unique within the class.'),
        ];
    }

    /**
     * @return array{name: string, level: int, description: string|null, sections: list<array{id: int|null, name: string, capacity: int|null, room: string|null, class_teacher_id: int|null}>, subjects: list<array{id: int, periods_per_week: int}>}
     */
    public function payload(): array
    {
        /** @var list<array<string, mixed>> $sections */
        $sections = $this->validated('sections', []);
        /** @var list<array<string, mixed>> $subjects */
        $subjects = $this->validated('subjects', []);

        $optionalInt = fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) $value;

        return [
            'name' => $this->string('name')->toString(),
            'level' => $this->integer('level'),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'sections' => array_map(fn (array $section): array => [
                'id' => $optionalInt($section['id'] ?? null),
                'name' => (string) $section['name'],
                'capacity' => $optionalInt($section['capacity'] ?? null),
                'room' => isset($section['room']) && $section['room'] !== '' ? (string) $section['room'] : null,
                'class_teacher_id' => $optionalInt($section['class_teacher_id'] ?? null),
            ], $sections),
            'subjects' => array_map(fn (array $subject): array => [
                'id' => (int) $subject['id'],
                'periods_per_week' => (int) $subject['periods_per_week'],
            ], $subjects),
        ];
    }

    private function schoolClass(): ?SchoolClass
    {
        $class = $this->route('class');

        return $class instanceof SchoolClass ? $class : null;
    }
}
