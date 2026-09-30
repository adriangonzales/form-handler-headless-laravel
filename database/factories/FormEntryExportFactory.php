<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormEntryExport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormEntryExport>
 */
class FormEntryExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'status' => FormEntryExport::STATUS_PENDING,
            'parameters' => [],
            'disk' => 'local',
            'path' => null,
            'filename' => fake()->slug().'-entries-'.now()->format('Y-m-d').'.csv',
            'row_count' => null,
            'error' => null,
            'completed_at' => null,
            'expires_at' => now()->addHours(FormEntryExport::RETENTION_HOURS),
        ];
    }

    /**
     * A finished export whose file has been written.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FormEntryExport::STATUS_COMPLETED,
            'path' => 'entry-exports/'.fake()->uuid().'.csv',
            'row_count' => 0,
            'completed_at' => now(),
        ]);
    }

    /**
     * An export past its retention window.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
