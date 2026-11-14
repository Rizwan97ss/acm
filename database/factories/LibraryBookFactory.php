<?php

namespace Database\Factories;

use App\Models\LibraryBook;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryBook>
 */
class LibraryBookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->isbn13(),
            'category' => 'Fiction',
            'copies_total' => 2,
            'copies_available' => 2,
        ];
    }
}
