<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Payroll\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Payroll::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => SalaryTypeEnum::MONTHLY,
            'period' => fake()->date(),
            'monthly_payroll_id' => null,
        ];
    }

    /**
     * Make this a biweekly payroll.
     */
    public function biweekly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SalaryTypeEnum::BIWEEKLY,
        ]);
    }
}
