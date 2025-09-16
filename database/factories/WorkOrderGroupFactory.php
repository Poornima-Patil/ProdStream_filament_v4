<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderGroup>
 */
class WorkOrderGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Group',
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['draft', 'active', 'completed', 'cancelled']),
            'planner_id' => \App\Models\User::factory(),
            'factory_id' => \App\Models\Factory::factory(),
            'planned_start_date' => $this->faker->dateTimeBetween('+1 days', '+7 days'),
            'planned_completion_date' => $this->faker->dateTimeBetween('+8 days', '+14 days'),
            'actual_start_date' => null,
            'actual_completion_date' => null,
        ];
    }
}
