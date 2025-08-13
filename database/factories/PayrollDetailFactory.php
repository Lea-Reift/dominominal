<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollDetail>
 */
class PayrollDetailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PayrollDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'payroll_id' => Payroll::factory(),
            'salary_id' => Salary::factory(),
        ];
    }
}
