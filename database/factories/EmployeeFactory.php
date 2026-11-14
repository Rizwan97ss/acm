<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'employee_no' => Str::upper(fake()->unique()->bothify('EMP-#####')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'is_teaching' => false,
            'designation' => 'Administrator',
            'employment_type' => EmploymentType::FullTime,
            'joined_on' => fake()->dateTimeBetween('-5 years')->format('Y-m-d'),
            'status' => EmployeeStatus::Active,
        ];
    }

    public function teaching(): static
    {
        return $this->state(fn (array $attributes) => ['is_teaching' => true, 'designation' => 'Teacher']);
    }
}
