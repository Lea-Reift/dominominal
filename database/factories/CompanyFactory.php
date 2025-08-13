<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentTypeEnum;
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Support\ValueObjects\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => fake()->numerify('###-#####-##'),
            'address' => fake()->address(),
            'phones' => collect([
                new Phone('mobile', fake()->phoneNumber())
            ]),
            'user_id' => User::factory(),
        ];
    }
}
