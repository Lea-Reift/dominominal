<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentTypeEnum;
use App\Modules\Company\Models\{Company, Employee};
use App\Support\ValueObjects\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => fake()->numerify('###-#######-#'),
            'address' => fake()->address(),
            'email' => fake()->safeEmail(),
            'phones' => collect([
                new Phone('mobile', fake()->phoneNumber())
            ]),
        ];
    }
}
