<?php

namespace App\Services\Academics;

use App\Models\Exam;
use App\Models\GradeScale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeScaleService
{
    /**
     * A conventional scale used until the school defines its own.
     *
     * @var list<array{grade: string, min_percentage: float, grade_point: float, remark: string, is_passing: bool}>
     */
    private const array STANDARD_BANDS = [
        ['grade' => 'A+', 'min_percentage' => 90, 'grade_point' => 4.0, 'remark' => 'Outstanding', 'is_passing' => true],
        ['grade' => 'A', 'min_percentage' => 80, 'grade_point' => 3.7, 'remark' => 'Excellent', 'is_passing' => true],
        ['grade' => 'B', 'min_percentage' => 70, 'grade_point' => 3.0, 'remark' => 'Very good', 'is_passing' => true],
        ['grade' => 'C', 'min_percentage' => 60, 'grade_point' => 2.0, 'remark' => 'Good', 'is_passing' => true],
        ['grade' => 'D', 'min_percentage' => 50, 'grade_point' => 1.0, 'remark' => 'Satisfactory', 'is_passing' => true],
        ['grade' => 'E', 'min_percentage' => 40, 'grade_point' => 0.5, 'remark' => 'Needs improvement', 'is_passing' => true],
        ['grade' => 'F', 'min_percentage' => 0, 'grade_point' => 0.0, 'remark' => 'Fail', 'is_passing' => false],
    ];

    /**
     * The school's default scale, creating the standard scale on first use.
     */
    public function defaultFor(int $schoolId): GradeScale
    {
        $scale = GradeScale::query()->where('is_default', true)->first()
            ?? GradeScale::query()->orderBy('id')->first();

        if ($scale !== null) {
            return $scale;
        }

        return DB::transaction(function () use ($schoolId): GradeScale {
            $scale = GradeScale::query()->create(['school_id' => $schoolId, 'name' => 'Standard', 'is_default' => true]);
            $scale->bands()->createMany(self::STANDARD_BANDS);

            return $scale;
        });
    }

    /**
     * @param  array{name: string, is_default: bool, bands: list<array{grade: string, min_percentage: float, grade_point: float|null, remark: string|null, is_passing: bool}>}  $data
     */
    public function save(GradeScale $scale, array $data): GradeScale
    {
        return DB::transaction(function () use ($scale, $data): GradeScale {
            $scale->fill(['name' => $data['name'], 'is_default' => $data['is_default']])->save();

            if ($data['is_default']) {
                GradeScale::query()->whereKeyNot($scale->id)->update(['is_default' => false]);
            }

            $scale->bands()->delete();
            $scale->bands()->createMany($data['bands']);

            return $scale;
        });
    }

    public function delete(GradeScale $scale): void
    {
        if (Exam::query()->where('grade_scale_id', $scale->id)->exists()) {
            throw ValidationException::withMessages(['scale' => __('Exams use this grading scale, so it cannot be deleted.')]);
        }

        if ($scale->is_default) {
            throw ValidationException::withMessages(['scale' => __('Make another scale the default before deleting this one.')]);
        }

        $scale->delete();
    }
}
