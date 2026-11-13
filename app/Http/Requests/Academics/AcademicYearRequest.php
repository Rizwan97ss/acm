<?php

namespace App\Http\Requests\Academics;

use App\Models\AcademicYear;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $year = $this->academicYear();

        return $year === null
            ? $this->user()->can('create', AcademicYear::class)
            : $this->user()->can('update', $year);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $year = $this->academicYear();

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('academic_years')->where('school_id', $this->schoolId())->ignore($year?->id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'terms' => ['present', 'array', 'max:6'],
            'terms.*.id' => ['nullable', 'integer', Rule::exists('terms', 'id')->where('academic_year_id', $year->id ?? 0)],
            'terms.*.name' => ['required', 'string', 'max:50', 'distinct'],
            'terms.*.starts_on' => ['required', 'date', 'after_or_equal:starts_on', 'before_or_equal:ends_on'],
            'terms.*.ends_on' => ['required', 'date', 'after:terms.*.starts_on', 'before_or_equal:ends_on'],
        ];
    }

    /**
     * Years of a school may not overlap, and neither may the terms of a year.
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

                $overlapping = AcademicYear::query()
                    ->when($this->academicYear(), fn ($query, AcademicYear $year) => $query->whereKeyNot($year->id))
                    ->where('starts_on', '<=', $this->input('ends_on'))
                    ->where('ends_on', '>=', $this->input('starts_on'))
                    ->first();

                if ($overlapping !== null) {
                    $validator->errors()->add('starts_on', __('These dates overlap :year.', ['year' => $overlapping->name]));
                }

                $terms = $this->collect('terms')->sortBy('starts_on')->values();

                foreach ($terms as $index => $term) {
                    $next = $terms->get($index + 1);

                    if ($next !== null && CarbonImmutable::parse($term['ends_on'])->gte(CarbonImmutable::parse($next['starts_on']))) {
                        $validator->errors()->add('terms', __(':first and :second overlap.', ['first' => $term['name'], 'second' => $next['name']]));
                    }
                }
            },
        ];
    }

    /**
     * @return array{name: string, starts_on: string, ends_on: string, terms: list<array{id: int|null, name: string, starts_on: string, ends_on: string}>}
     */
    public function payload(): array
    {
        /** @var list<array<string, mixed>> $terms */
        $terms = $this->validated('terms', []);

        return [
            'name' => $this->string('name')->toString(),
            'starts_on' => $this->string('starts_on')->toString(),
            'ends_on' => $this->string('ends_on')->toString(),
            'terms' => array_map(fn (array $term): array => [
                'id' => isset($term['id']) ? (int) $term['id'] : null,
                'name' => (string) $term['name'],
                'starts_on' => (string) $term['starts_on'],
                'ends_on' => (string) $term['ends_on'],
            ], $terms),
        ];
    }

    private function academicYear(): ?AcademicYear
    {
        $year = $this->route('academic_year');

        return $year instanceof AcademicYear ? $year : null;
    }

    private function schoolId(): ?int
    {
        return app(CurrentSchool::class)->id();
    }
}
