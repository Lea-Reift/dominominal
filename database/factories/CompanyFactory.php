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
        $documentType = fake()->randomElement(DocumentTypeEnum::cases());

        return [
            'name' => fake()->company(),
            'document_type' => $documentType,
            'document_number' => $this->generateDocumentNumber($documentType),
            'address' => fake()->address(),
            'phones' => collect([
                new Phone('mobile', fake()->phoneNumber())
            ]),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Generate a document number based on document type
     */
    private function generateDocumentNumber(DocumentTypeEnum $documentType): string
    {
        return match($documentType) {
            DocumentTypeEnum::RNC => fake()->numerify('#########'),
            DocumentTypeEnum::IDENTIFICATION => fake()->numerify('###-#######-#'),
            DocumentTypeEnum::PASSPORT => fake()->bothify('**********'),
        };
    }

    /**
     * Configure the factory to create companies with RNC document type
     */
    public function rnc(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => fake()->numerify('#########'),
        ]);
    }

    /**
     * Configure the factory to create companies with IDENTIFICATION document type
     */
    public function identification(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => fake()->numerify('###-#######-#'),
        ]);
    }

    /**
     * Configure the factory to create companies with PASSPORT document type
     */
    public function passport(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentTypeEnum::PASSPORT,
            'document_number' => fake()->bothify('**********'),
        ]);
    }
}
