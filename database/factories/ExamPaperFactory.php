<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPaper>
 */
class ExamPaperFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'school_id' => fn (array $attributes) => Exam::query()->withoutGlobalScopes()->whereKey($attributes['exam_id'])->value('school_id'),
            'school_class_id' => fn (array $attributes) => SchoolClass::factory()->state(['school_id' => $attributes['school_id']]),
            'subject_id' => fn (array $attributes) => Subject::factory()->state(['school_id' => $attributes['school_id']]),
            'max_marks' => 100,
            'pass_marks' => 40,
        ];
    }
}
