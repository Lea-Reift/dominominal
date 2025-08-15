<?php

declare(strict_types=1);

use App\Enums\DocumentTypeEnum;
use App\Enums\SalaryDistributionFormatEnum;
use App\Enums\SalaryTypeEnum;
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Resources\CompanyResource\RelationManagers\EmployeesRelationManager;
use Livewire\Livewire;
use App\Modules\Company\Resources\CompanyResource\Pages\ViewCompany;
use Filament\Tables\Actions\CreateAction;

use function Pest\Laravel\actingAs;

describe('Employees Relation Manager', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);

        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Company',
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20123456789',
        ]);
    });

    test('can render employees relation manager', function () {
        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->assertSuccessful()
            ->assertSee('Empleados');
    });

    test('displays employees in table', function () {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Juan',
            'surname' => 'Pérez García',
            'job_title' => 'Desarrollador',
            'email' => 'juan.perez@test.com',
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '123-4567890-1',
        ]);

        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->assertCanSeeTableRecords([$employee])
            ->assertSee('Juan Pérez García')
            ->assertSee(DocumentTypeEnum::IDENTIFICATION->getAcronym() . ': 123-4567890-1');
    });

    test('can search employees by document type', function () {
        $searchableEmployee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'María',
            'surname' => 'González López',
            'job_title' => 'Gerente',
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '123-4567890-1',
        ]);

        $otherEmployee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Carlos',
            'surname' => 'Mendoza Silva',
            'job_title' => 'Analista',
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '301234567',
        ]);

        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->assertCanSeeTableRecords([$searchableEmployee, $otherEmployee]);
    });


    test('can create new employee', function () {
        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->assertTableActionExists(CreateAction::getDefaultName())
            ->callTableAction('create', data: [
                'name' => 'Nuevo',
                'surname' => 'Empleado Test',
                'job_title' => 'Tester QA',
                'address' => 'Av. Test 123',
                'email' => 'nuevo.empleado@test.com',
                'document_type' => DocumentTypeEnum::IDENTIFICATION->value,
                'document_number' => '998-8776600-1',
                'salary.amount' => '50000.00',
                'salary.type' => SalaryTypeEnum::BIWEEKLY->value,
                'salary.distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE->value,
                'salary.distribution_value' => '50.00',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas(Employee::class, [
            'company_id' => $this->company->id,
            'name' => 'Nuevo',
            'surname' => 'Empleado Test',
            'job_title' => 'Tester QA',
            'email' => 'nuevo.empleado@test.com',
        ]);
    });

    test('can edit existing employee', function () {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Original',
            'surname' => 'Name',
            'job_title' => 'Original Title',
            'email' => 'original@test.com',
        ]);

        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->callTableAction('edit', $employee, data: [
                'name' => 'Updated',
                'surname' => 'Name Changed',
                'job_title' => 'New Title',
                'email' => 'updated@test.com',
                'document_type' => DocumentTypeEnum::IDENTIFICATION->value,
                'document_number' => '123-4567890-1',
                'salary.amount' => '45000.00',
                'salary.type' => SalaryTypeEnum::BIWEEKLY->value,
                'salary.distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE->value,
                'salary.distribution_value' => '50.00',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas(Employee::class, [
            'id' => $employee->id,
            'name' => 'Updated',
            'surname' => 'Name Changed',
            'job_title' => 'New Title',
            'email' => 'updated@test.com',
        ]);
    });


    test('can sort employees by name', function () {
        $employeeA = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Ana',
            'surname' => 'Álvarez',
        ]);

        $employeeZ = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Zara',
            'surname' => 'Zapata',
        ]);

        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->sortTable('full_name')
            ->assertCanSeeTableRecords([$employeeA, $employeeZ], inOrder: true);
    });

    test('displays no employees message when company has no employees', function () {
        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->assertCountTableRecords(0);
    });


    test('can handle pagination with many employees', function () {
        Employee::factory()->count(15)->create(['company_id' => $this->company->id]);

        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->assertCountTableRecords(15);
    });

    test('validates required fields when creating employee', function () {
        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->callTableAction('create', data: [
                'name' => '',
                'surname' => '',
                'document_type' => '',
                'document_number' => '',
                'salary.amount' => '',
                'salary.type' => '',
            ])
            ->assertHasTableActionErrors(['name', 'surname', 'document_type', 'document_number', 'salary.amount', 'salary.type']);
    });

    test('validates email format when creating employee', function () {
        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'Test',
                'surname' => 'User',
                'email' => 'invalid-email',
                'job_title' => 'Tester',
                'address' => 'Test Address',
                'document_type' => DocumentTypeEnum::IDENTIFICATION->value,
                'document_number' => '123-4567890-1',
                'salary.amount' => '50000.00',
                'salary.type' => SalaryTypeEnum::BIWEEKLY->value,
                'salary.distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE->value,
                'salary.distribution_value' => '50.00',
            ])
            ->assertHasTableActionErrors(['email']);
    });

    test('creates employee successfully with all required fields', function () {
        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'New',
                'surname' => 'Employee',
                'email' => 'new.employee@test.com',
                'job_title' => 'Developer',
                'address' => 'New Address',
                'document_type' => DocumentTypeEnum::IDENTIFICATION->value,
                'document_number' => '876-5432100-1',
                'salary.amount' => '50000.00',
                'salary.type' => SalaryTypeEnum::BIWEEKLY->value,
                'salary.distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE->value,
                'salary.distribution_value' => '50.00',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas(Employee::class, [
            'company_id' => $this->company->id,
            'name' => 'New',
            'surname' => 'Employee',
            'email' => 'new.employee@test.com',
        ]);
    });


    test('displays correct employee information in table columns', function () {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Column Test',
            'surname' => 'Employee',
            'job_title' => 'QA Engineer',
            'address' => 'Limited Address Content',
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '55667788',
        ]);

        Livewire::test(EmployeesRelationManager::class, [
            'ownerRecord' => $this->company,
            'pageClass' => ViewCompany::class,
        ])
            ->assertCanSeeTableRecords([$employee])
            ->assertSee('Column Test Employee')
            ->assertSee(DocumentTypeEnum::IDENTIFICATION->getAcronym() . ': 55667788')
            ->assertSee('Limited Address');
    });
});
