<?php

declare(strict_types=1);

use App\Enums\DocumentTypeEnum;
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Support\ValueObjects\Phone;

use function Pest\Laravel\{actingAs, assertDatabaseHas};

describe('Employee Model Operations', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);

        $this->company = Company::factory()->create();
    });

    test('can create employee with required fields', function () {
        $employee = Employee::create([
            'company_id' => $this->company->id,
            'name' => 'Juan',
            'surname' => 'Pérez García',
            'job_title' => 'Desarrollador Senior',
            'address' => 'Av. Principal 456',
            'email' => 'juan.perez@test.com',
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '12345678',
        ]);

        expect($employee)->not->toBeNull();
        expect($employee->name)->toBe('Juan');
        expect($employee->surname)->toBe('Pérez García');
        expect($employee->job_title)->toBe('Desarrollador Senior');
        expect($employee->address)->toBe('Av. Principal 456');
        expect($employee->email)->toBe('juan.perez@test.com');
        expect($employee->company_id)->toBe($this->company->id);

        assertDatabaseHas(Employee::class, [
            'company_id' => $this->company->id,
            'name' => 'Juan',
            'surname' => 'Pérez García',
            'job_title' => 'Desarrollador Senior',
            'email' => 'juan.perez@test.com',
        ]);
    });

    test('can create employee with phones', function () {
        $phones = collect([
            new Phone('Personal', '051123456789'),
            new Phone('Emergencia', '051987654321'),
        ]);

        $employee = Employee::create([
            'company_id' => $this->company->id,
            'name' => 'María',
            'surname' => 'López',
            'job_title' => 'Gerente',
            'address' => 'Calle Test 123',
            'email' => 'maria@test.com',
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '87654321',
            'phones' => $phones,
        ]);

        expect($employee->phones)->toHaveCount(2);
        expect($employee->phones->first())->toBeInstanceOf(Phone::class);
        expect($employee->phones->first()->number)->toBe('051123456789');
        expect($employee->phones->last()->type)->toBe('Emergencia');
    });

    test('initializes empty phones collection when none provided', function () {
        $employee = Employee::create([
            'company_id' => $this->company->id,
            'name' => 'Carlos',
            'surname' => 'Mendoza',
            'job_title' => 'Analista',
            'address' => 'Jr. Prueba 789',
            'email' => 'carlos@test.com',
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '11223344',
        ]);

        expect($employee->phones)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($employee->phones)->toHaveCount(0);
    });

    test('can update employee information', function () {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Original Name',
            'job_title' => 'Original Title',
            'email' => 'original@test.com',
        ]);

        $employee->update([
            'name' => 'Updated Name',
            'job_title' => 'Updated Title',
            'email' => 'updated@test.com',
        ]);

        expect($employee->name)->toBe('Updated Name');
        expect($employee->job_title)->toBe('Updated Title');
        expect($employee->email)->toBe('updated@test.com');

        assertDatabaseHas(Employee::class, [
            'id' => $employee->id,
            'name' => 'Updated Name',
            'job_title' => 'Updated Title',
            'email' => 'updated@test.com',
        ]);
    });

    test('can update employee contact information', function () {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'address' => 'Old Address',
            'email' => 'old@test.com',
        ]);

        $employee->update([
            'address' => 'New Address',
            'email' => 'new@test.com',
        ]);

        expect($employee->address)->toBe('New Address');
        expect($employee->email)->toBe('new@test.com');
    });

    test('can update employee document information', function () {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '11111111',
        ]);

        $employee->update([
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20111222333',
        ]);

        expect($employee->document_type)->toBe(DocumentTypeEnum::RNC);
        expect($employee->document_number)->toBe('20111222333');
    });

    test('can update employee phones', function () {
        $originalPhones = collect([new Phone('personal', '111111111')]);
        $updatedPhones = collect([
            new Phone('personal', '222222222'),
            new Phone('work', '333333333'),
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'phones' => $originalPhones,
        ]);

        $employee->update(['phones' => $updatedPhones]);

        expect($employee->phones)->toHaveCount(2);
        expect($employee->phones->first()->number)->toBe('222222222');
        expect($employee->phones->last()->type)->toBe('work');
    });

    test('can change employee company', function () {
        $newCompany = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $employee->update(['company_id' => $newCompany->id]);

        expect($employee->company_id)->toBe($newCompany->id);
        expect($employee->company->id)->toBe($newCompany->id);
    });

    test('can delete employee', function () {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $employeeId = $employee->id;

        $employee->delete();

        expect(Employee::find($employeeId))->toBeNull();
    });

    test('can handle different job titles', function () {
        $titles = [
            'Desarrollador Frontend',
            'Diseñador UX/UI',
            'Project Manager',
            'Quality Assurance Tester',
            'DevOps Engineer'
        ];

        foreach ($titles as $title) {
            $employee = Employee::factory()->create([
                'company_id' => $this->company->id,
                'job_title' => $title,
            ]);

            expect($employee->job_title)->toBe($title);
        }
    });

    test('can handle different document types', function () {
        $employeeDni = Employee::factory()->create([
            'company_id' => $this->company->id,
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '12345678',
        ]);

        $employeeRuc = Employee::factory()->create([
            'company_id' => $this->company->id,
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20123456789',
        ]);

        expect($employeeDni->document_type)->toBe(DocumentTypeEnum::IDENTIFICATION);
        expect($employeeRuc->document_type)->toBe(DocumentTypeEnum::RNC);
        expect($employeeDni->document_number)->toHaveLength(8);
        expect($employeeRuc->document_number)->toHaveLength(11);
    });

    test('can handle email format variations', function () {
        $emails = [
            'user@example.com',
            'test.user+tag@domain.co.uk',
            'firstname.lastname@company.org',
        ];

        foreach ($emails as $email) {
            $employee = Employee::factory()->create([
                'company_id' => $this->company->id,
                'email' => $email,
            ]);

            expect($employee->email)->toBe($email);
        }
    });

    test('maintains referential integrity with company', function () {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        expect($employee->company)->not->toBeNull();
        expect($employee->company->id)->toBe($this->company->id);
        expect($employee->company_id)->toBe($this->company->id);
    });
});
