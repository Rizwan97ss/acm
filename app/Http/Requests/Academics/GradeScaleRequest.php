<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\GradeScale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GradeScaleRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        $scale = $this->route('grade_scale');

        return $scale instanceof GradeScale
            ? $this->user()->can('update', $scale)
            : $this->user()->can('create', GradeScale::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $scale = $this->route('grade_scale');

        return [
            'name' => ['required', 'string', 'max:100', $this->uniqueInSchool('grade_scales', 'name', $scale instanceof GradeScale ? $scale->id : null)],
            'is_default' => ['required', 'boolean'],
            'bands' => ['required', 'array', 'min:2', 'max:20'],
            'bands.*.grade' => ['required', 'string', 'max:8', 'distinct'],
            'bands.*.min_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'distinct'],
            'bands.*.grade_point' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'bands.*.remark' => ['nullable', 'string', 'max:50'],
            'bands.*.is_passing' => ['required', 'boolean'],
        ];
    }

    /**
     * The lowest band must start at 0% so every percentage maps to a grade.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $minimums = array_map(fn (array $band): float => (float) ($band['min_percentage'] ?? -1), (array) $this->input('bands', []));

                if ($minimums !== [] && min($minimums) !== 0.0) {
                    $validator->errors()->add('bands', __('The lowest grade must start at 0%.'));
                }
            },
        ];
    }

    /**
     * @return array{name: string, is_default: bool, bands: list<array{grade: string, min_percentage: float, grade_point: float|null, remark: string|null, is_passing: bool}>}
     */
    public function payload(): array
    {
        /** @var list<array<string, mixed>> $bands */
        $bands = $this->validated('bands');

        return [
            'name' => $this->string('name')->toString(),
            'is_default' => $this->boolean('is_default'),
            'bands' => array_map(fn (array $band): array => [
                'grade' => (string) $band['grade'],
                'min_percentage' => (float) $band['min_percentage'],
                'grade_point' => isset($band['grade_point']) && $band['grade_point'] !== '' ? (float) $band['grade_point'] : null,
                'remark' => isset($band['remark']) && $band['remark'] !== '' ? (string) $band['remark'] : null,
                'is_passing' => filter_var($band['is_passing'], FILTER_VALIDATE_BOOL),
            ], $bands),
        ];
    }
}
