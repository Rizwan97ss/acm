<?php

namespace Database\Factories;

use App\Models\ContactInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactInquiry>
 */
class ContactInquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('+92 3## #######'),
            'organization' => fake()->optional()->company().' School',
            'role' => fake()->optional()->randomElement(['Principal', 'Administrator', 'Owner', 'IT lead']),
            'school_size' => fake()->optional()->randomElement(array_keys(ContactInquiry::SCHOOL_SIZES)),
            'interest' => fake()->randomElement(array_keys(ContactInquiry::INTERESTS)),
            'message' => fake()->paragraph(),
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }
}
