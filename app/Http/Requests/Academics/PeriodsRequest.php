<?php

namespace App\Http\Requests\Academics;

use App\Services\Tenancy\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The bell schedule is part of timetable configuration, so it needs timetables.edit.
 */
class PeriodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('timetables.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'periods' => ['present', 'array', 'max:20'],
            'periods.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('periods', 'id')->where('school_id', app(CurrentSchool::class)->id())],
            'periods.*.name' => ['required', 'string', 'max:30'],
            'periods.*.starts_at' => ['required', 'date_format:H:i'],
            'periods.*.ends_at' => ['required', 'date_format:H:i', 'after:periods.*.starts_at'],
            'periods.*.is_break' => ['required', 'boolean'],
        ];
    }

    /**
     * Periods must be listed in time order without overlapping.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $periods = array_values($this->input('periods', []));

                foreach ($periods as $index => $period) {
                    $next = $periods[$index + 1] ?? null;

                    if ($next !== null && $period['ends_at'] > $next['starts_at']) {
                        $validator->errors()->add("periods.{$index}.ends_at", __(':first ends after :second starts.', ['first' => $period['name'], 'second' => $next['name']]));
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{id: int|null, name: string, starts_at: string, ends_at: string, is_break: bool}>
     */
    public function payload(): array
    {
        /** @var list<array<string, mixed>> $periods */
        $periods = $this->validated('periods', []);

        return array_map(fn (array $period): array => [
            'id' => isset($period['id']) ? (int) $period['id'] : null,
            'name' => (string) $period['name'],
            'starts_at' => (string) $period['starts_at'],
            'ends_at' => (string) $period['ends_at'],
            'is_break' => filter_var($period['is_break'], FILTER_VALIDATE_BOOL),
        ], $periods);
    }
}
