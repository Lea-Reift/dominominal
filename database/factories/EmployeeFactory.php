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
        $documentType = fake()->randomElement(DocumentTypeEnum::cases());

        return [
            'company_id' => Company::factory(),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'job_title' => fake()->jobTitle(),
            'document_type' => $documentType,
            'document_number' => $this->generateDocumentNumber($documentType),
            'address' => fake()->address(),
            'email' => fake()->safeEmail(),
            'phones' => collect([
                new Phone('mobile', fake()->phoneNumber())
            ]),
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
     * Configure the factory to create employees with RNC document type
     */
    public function rnc(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => fake()->numerify('#########'),
        ]);
    }

    /**
     * Configure the factory to create employees with IDENTIFICATION document type
     */
    public function identification(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => fake()->numerify('###-#######-#'),
        ]);
    }

    /**
     * Configure the factory to create employees with PASSPORT document type
     */
    public function passport(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentTypeEnum::PASSPORT,
            'document_number' => fake()->bothify('**********'),
        ]);
    }
}
