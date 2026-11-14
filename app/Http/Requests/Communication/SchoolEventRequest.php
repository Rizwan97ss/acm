<?php

namespace App\Http\Requests\Communication;

use App\Enums\Audience;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\SchoolEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolEventRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        return $this->route('event') instanceof SchoolEvent
            ? $this->user()->can('events.edit')
            : $this->user()->can('events.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:150'],
            'all_day' => ['boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'audience' => ['required', Rule::enum(Audience::class)],
            'school_class_id' => [
                'nullable', 'integer', $this->existsInSchool('school_classes'),
                Rule::prohibitedIf(in_array($this->input('audience'), [Audience::Everyone->value, Audience::Staff->value], true)),
            ],
        ];
    }

    /**
     * All-day events span whole days so they show on every date they cover.
     *
     * @return array{title: string, description: string|null, location: string|null, all_day: bool, starts_at: CarbonImmutable, ends_at: CarbonImmutable, audience: string, school_class_id: int|null}
     */
    public function payload(): array
    {
        $allDay = $this->boolean('all_day');
        $start = CarbonImmutable::parse((string) $this->validated('starts_at'));
        $end = CarbonImmutable::parse((string) $this->validated('ends_at'));

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'location' => $this->validated('location'),
            'all_day' => $allDay,
            'starts_at' => $allDay ? $start->startOfDay() : $start,
            'ends_at' => $allDay ? $end->endOfDay()->startOfSecond() : $end,
            'audience' => (string) $this->validated('audience'),
            'school_class_id' => $this->validated('school_class_id') !== null ? (int) $this->validated('school_class_id') : null,
        ];
    }
}
