<?php

namespace App\Http\Requests\Academics;

use App\Enums\Weekday;
use App\Models\TimetableEntry;
use App\Services\Academics\TeacherDirectory;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('entry');

        return $entry instanceof TimetableEntry
            ? $this->user()->can('update', $entry)
            : $this->user()->can('create', TimetableEntry::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = (int) app(CurrentSchool::class)->id();
        $schoolDays = array_map(
            fn (Weekday $day): int => $day->value,
            Weekday::forSchoolWeek((string) app(SettingsService::class)->current('academic.school_week')),
        );

        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'day_of_week' => ['required', 'integer', Rule::in($schoolDays)],
            'period_id' => ['required', 'integer', Rule::exists('periods', 'id')->where('school_id', $schoolId)],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('school_id', $schoolId)->where('is_active', true)],
            'teacher_id' => ['nullable', 'integer', Rule::in(app(TeacherDirectory::class)->query($schoolId)->pluck('id')->all())],
            'room' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teacher_id.in' => __('Choose an active teacher of this school.'),
            'day_of_week.in' => __('That day is not a school day.'),
        ];
    }

    /**
     * @return array{academic_year_id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int|null, room: string|null}
     */
    public function payload(): array
    {
        return [
            'academic_year_id' => $this->integer('academic_year_id'),
            'section_id' => $this->integer('section_id'),
            'day_of_week' => $this->integer('day_of_week'),
            'period_id' => $this->integer('period_id'),
            'subject_id' => $this->integer('subject_id'),
            'teacher_id' => $this->filled('teacher_id') ? $this->integer('teacher_id') : null,
            'room' => $this->filled('room') ? $this->string('room')->trim()->toString() : null,
        ];
    }
}
