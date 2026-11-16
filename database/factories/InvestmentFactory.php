<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\Investor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investor_id' => Investor::factory(),
            'amount' => fake()->randomFloat(2, 1_000, 1_000_000),
            'invested_on' => fake()->unique()->dateTimeBetween('-2 years')->format('Y-m-d'),
        ];
    }
}
