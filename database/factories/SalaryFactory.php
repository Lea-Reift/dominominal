<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalaryDistributionFormatEnum;
use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salary>
 */
class SalaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Salary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'amount' => fake()->randomFloat(2, 20000, 100000),
            'type' => fake()->randomElement(SalaryTypeEnum::cases()),
            'distribution_format' => fake()->randomElement(SalaryDistributionFormatEnum::cases()),
            'distribution_value' => fake()->randomFloat(2, 30, 70),
        ];
    }
}
