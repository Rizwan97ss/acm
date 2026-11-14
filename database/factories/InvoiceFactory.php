<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => fn (array $attributes) => Student::factory()->state(['school_id' => $attributes['school_id']]),
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->state(['school_id' => $attributes['school_id']]),
            'number' => Str::upper(fake()->unique()->bothify('INV-######')),
            'title' => 'Tuition',
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addMonth()->toDateString(),
            'subtotal' => 10000,
            'discount' => 0,
            'total' => 10000,
        ];
    }
}
