<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'setting' => 'app',
            'name' => fake()->word(),
            'value' => fake()->word(),
            'is_encrypted' => false,
        ];
    }

    /**
     * Create a setup_completed setting for app.
     */
    public function setupCompleted(bool $completed = true): static
    {
        return $this->state(fn (array $attributes): array => [
            'setting' => 'app',
            'name' => 'setup_completed',
            'value' => $completed,
        ]);
    }

    /**
     * Create the setup is_completed setting for setup page.
     */
    public function setupIsCompleted(bool $completed = false): static
    {
        return $this->state(fn (array $attributes): array => [
            'setting' => 'setup',
            'name' => 'is_completed',
            'value' => $completed,
        ]);
    }
}
