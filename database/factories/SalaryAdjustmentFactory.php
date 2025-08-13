<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryAdjustment>
 */
class SalaryAdjustmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = SalaryAdjustment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'name' => fake()->words(2, true),
            'parser_alias' => fake()->unique()->slug(),
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => fake()->randomFloat(2, 100, 1000),
            'requires_custom_value' => false,
            'ignore_in_deductions' => true,
            'is_absolute_adjustment' => false,
        ];
    }

    /**
     * Make this an addition (income) adjustment.
     */
    public function addition(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SalaryAdjustmentTypeEnum::INCOME,
        ]);
    }

    /**
     * Make this a deduction adjustment.
     */
    public function deduction(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION,
        ]);
    }

    /**
     * Make this a fixed value adjustment.
     */
    public function fixed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
        ]);
    }

    /**
     * Make this a percentage adjustment.
     */
    public function percentage(): static
    {
        return $this->state(fn (array $attributes): array => [
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
        ]);
    }
}
