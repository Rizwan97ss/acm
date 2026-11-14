<?php

namespace Database\Factories;

use App\Enums\AdmissionStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdmissionApplication>
 */
class AdmissionApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'application_no' => Str::upper(fake()->unique()->bothify('APP-######')),
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->state(['school_id' => $attributes['school_id']]),
            'school_class_id' => fn (array $attributes) => SchoolClass::factory()->state(['school_id' => $attributes['school_id']]),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-15 years', '-5 years')->format('Y-m-d'),
            'guardian_name' => fake()->name(),
            'guardian_relationship' => GuardianRelationship::Mother,
            'guardian_phone' => fake()->numerify('+1 555 ### ####'),
            'guardian_email' => fake()->unique()->safeEmail(),
            'status' => AdmissionStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AdmissionStatus::Approved]);
    }
}
