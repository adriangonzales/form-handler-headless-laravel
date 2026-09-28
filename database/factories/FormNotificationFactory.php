<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormNotification>
 */
class FormNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'type' => fake()->randomElement(['email', 'sms']),
            'value' => fake()->word(),
            'enabled' => fake()->boolean(),
            'error' => fake()->word(),
        ];
    }
}
