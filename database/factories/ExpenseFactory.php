<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'expense_category_id' => fn (array $attributes) => ExpenseCategory::query()->create(['school_id' => $attributes['school_id'], 'name' => 'Supplies '.Str::random(6)])->id,
            'number' => Str::upper(fake()->unique()->bothify('EXP-#####')),
            'payee' => fake()->company(),
            'description' => 'Stationery',
            'amount' => 5000,
            'spent_on' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
        ];
    }
}
