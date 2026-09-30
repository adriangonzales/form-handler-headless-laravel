<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormEntry>
 */
class FormEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'input' => [],
            'ip' => fake()->word(),
            'ip_location_display' => fake()->word(),
            'referer' => fake()->word(),
            'user_agent' => fake()->word(),
            'user_agent_display' => fake()->word(),
            'spam' => fake()->boolean(),
            'spam_score' => fake()->randomFloat(2, 0, 1),
            'spam_reason' => fake()->word(),
            'starred' => fake()->boolean(),
            'read_at' => fake()->dateTime(),
        ];
    }
}
