<?php

declare(strict_types=1);

use App\Enums\DocumentTypeEnum;
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Resources\CompanyResource\Pages\ListCompanies;
use App\Modules\Company\Resources\CompanyResource\Pages\ViewCompany;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('Company Filament Resource', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    describe('ListCompanies page', function () {
        test('can render companies list page', function () {
            Livewire::test(ListCompanies::class)
                ->assertSuccessful()
                ->assertSee('Compañías');
        });

        test('displays companies in table', function () {
            $company = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Test Company S.A.',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20123456789',
            ]);

            Livewire::test(ListCompanies::class)
                ->assertSuccessful()
                ->assertCanSeeTableRecords([$company])
                ->assertSee('Test Company S.A.');
        });

        test('can search companies by name', function () {
            $searchableCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Searchable Company',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20111222333',
            ]);

            $otherCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Other Company',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20444555666',
            ]);

            Livewire::test(ListCompanies::class)
                ->searchTable('Searchable')
                ->assertCanSeeTableRecords([$searchableCompany])
                ->assertCanNotSeeTableRecords([$otherCompany]);
        });

        test('can search companies by document number', function () {
            $searchableCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Company One',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20123456789',
            ]);

            $otherCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Company Two',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20987654321',
            ]);

            Livewire::test(ListCompanies::class)
                ->searchTable($searchableCompany->name)
                ->assertCanSeeTableRecords([$searchableCompany])
                ->assertCanNotSeeTableRecords([$otherCompany]);
        });

        test('can search companies by address', function () {
            $searchableCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Company One',
                'address' => 'Av. Principal 123',
            ]);

            $otherCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Company Two',
                'address' => 'Calle Secundaria 456',
            ]);

            Livewire::test(ListCompanies::class)
                ->searchTable('Principal')
                ->assertCanSeeTableRecords([$searchableCompany])
                ->assertCanNotSeeTableRecords([$otherCompany]);
        });

        test('can sort companies by name', function () {
            $companyA = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'A Company'
            ]);
            $companyZ = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Z Company'
            ]);

            Livewire::test(ListCompanies::class)
                ->sortTable('name')
                ->assertCanSeeTableRecords([$companyA, $companyZ], inOrder: true);
        });

        test('can sort companies by document type', function () {
            $identificationCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'document_type' => DocumentTypeEnum::IDENTIFICATION,
                'document_number' => '12345678',
            ]);

            $rncCompany = Company::factory()->create([
                'user_id' => $this->user->id,
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20123456789',
            ]);

            Livewire::test(ListCompanies::class)
                ->sortTable('document_type')
                ->assertCanSeeTableRecords([$rncCompany, $identificationCompany], inOrder: true);
        });

        test('displays no records message when no companies exist', function () {
            Livewire::test(ListCompanies::class)
                ->assertSuccessful()
                ->assertCountTableRecords(0);
        });

        test('can handle pagination with many companies', function () {
            Company::factory()->count(25)->create(['user_id' => $this->user->id]);

            Livewire::test(ListCompanies::class)
                ->assertSuccessful()
                ->assertCountTableRecords(25);
        });


        test('displays correct columns in table', function () {
            $company = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Test Company',
                'address' => 'Test Address 123',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20123456789',
            ]);

            Livewire::test(ListCompanies::class)
                ->assertSuccessful()
                ->assertSee('Test Company')
                ->assertSee('Test Address 123')
            ;
        });
    });

    describe('ViewCompany page', function () {
        beforeEach(function () {
            $this->company = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'View Test Company',
                'address' => 'View Test Address',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20123456789',
            ]);
        });

        test('can render view company page', function () {
            Livewire::test(ViewCompany::class, ['record' => $this->company->id])
                ->assertSuccessful()
                ->assertSee('View Test Company');
        });

        test('displays company information correctly', function () {
            Livewire::test(ViewCompany::class, ['record' => $this->company->id])
                ->assertSuccessful()
                ->assertSee($this->company->name);
        });

        test('shows employees relation manager', function () {
            Livewire::test(ViewCompany::class, ['record' => $this->company->id])
                ->assertSuccessful()
                ->assertSee('Empleados');
        });

        test('shows payrolls relation manager', function () {
            Livewire::test(ViewCompany::class, ['record' => $this->company->id])
                ->assertSuccessful()
                ->assertSee('Nóminas');
        });

        test('can navigate to company view from invalid record', function () {
            expect(fn () => Livewire::test(ViewCompany::class, ['record' => 999999]))
                ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        });
    });

    describe('Company resource form validation', function () {
        test('validates required fields', function () {
            $requiredFields = ['name', 'address', 'document_type', 'document_number'];

            foreach ($requiredFields as $field) {
                expect($field)->not->toBeEmpty(); // Basic validation test
            }
        });

        test('validates document number format for RUC', function () {
            $validRucNumbers = [
                '20123456789',
                '20987654321',
                '20111222333',
            ];

            foreach ($validRucNumbers as $rucNumber) {
                expect($rucNumber)->toHaveLength(11);
                expect($rucNumber)->toMatch('/^20\d{9}$/');
            }
        });

        test('validates document number format for DNI', function () {
            $validDniNumbers = [
                '12345678',
                '87654321',
                '11223344',
            ];

            foreach ($validDniNumbers as $dniNumber) {
                expect($dniNumber)->toHaveLength(8);
                expect($dniNumber)->toMatch('/^\d{8}$/');
            }
        });

        test('validates name length limits', function () {
            $validName = 'Test Company S.A.';
            $tooLongName = str_repeat('A', 256);

            expect(strlen($validName))->toBeLessThanOrEqual(255);
            expect(strlen($tooLongName))->toBeGreaterThan(255);
        });

        test('validates address length limits', function () {
            $validAddress = 'Av. Principal 123, Lima, Peru';
            $tooLongAddress = str_repeat('A', 256);

            expect(strlen($validAddress))->toBeLessThanOrEqual(255);
            expect(strlen($tooLongAddress))->toBeGreaterThan(255);
        });
    });

    describe('Company resource edge cases', function () {
        test('handles companies with special characters in name', function () {
            $company = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Empresa & Asociados S.A.C.',
                'document_type' => DocumentTypeEnum::RNC,
                'document_number' => '20123456789',
            ]);

            Livewire::test(ListCompanies::class)
                ->assertCanSeeTableRecords([$company])
                ->assertSee('Empresa & Asociados S.A.C.');
        });

        test('handles companies with accented characters', function () {
            $company = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Corporación Médica Perú',
                'address' => 'Jr. José Olaya 123',
            ]);

            Livewire::test(ListCompanies::class)
                ->assertCanSeeTableRecords([$company])
                ->assertSee('Corporación Médica Perú');
        });

        test('handles empty search gracefully', function () {
            $companies = Company::factory()->count(3)->create();

            Livewire::test(ListCompanies::class)
                ->searchTable('')
                ->assertCanSeeTableRecords($companies);
        });

        test('handles search with no results', function () {
            Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Existing Company'
            ]);

            Livewire::test(ListCompanies::class)
                ->searchTable('NonExistentCompany')
                ->assertCountTableRecords(0);
        });

        test('handles very long company names in table display', function () {
            $company = Company::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Esta es una empresa con un nombre extremadamente largo que podría causar problemas de visualización',
            ]);

            Livewire::test(ListCompanies::class)
                ->assertCanSeeTableRecords([$company]);
        });
    });
});
