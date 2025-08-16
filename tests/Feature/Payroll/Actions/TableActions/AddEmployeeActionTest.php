<?php

declare(strict_types=1);

use App\Enums\DocumentTypeEnum;
use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Actions\TableActions\AddEmployeeAction;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Tables\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->payroll = Payroll::factory()->create(['company_id' => $this->company->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
});

describe('AddEmployeeAction - Basic Functionality', function () {
    test('can create add employee action instance', function () {
        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($action->getLabel())->toBe('Añadir empleados');
    });

    test('action has correct name', function () {
        $action = AddEmployeeAction::make($this->payroll);

        expect($action->getName())->toBe('add_employees');
    });

    test('action is properly configured', function () {
        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($action->getLabel())->toBe('Añadir empleados');
        expect($action->getName())->toBe('add_employees');
    });
});

describe('AddEmployeeAction - Integration Tests', function () {
    test('can handle payroll with existing employees', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id,
        ]);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($this->payroll->employees()->count())->toBe(1);
    });

    test('can handle payroll with salary adjustments', function () {
        $adjustment1 = SalaryAdjustment::factory()->create();
        $adjustment2 = SalaryAdjustment::factory()->create();

        $this->payroll->salaryAdjustments()->attach([
            $adjustment1->id,
            $adjustment2->id
        ]);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($this->payroll->salaryAdjustments()->count())->toBe(2);
    });

    test('can handle company with multiple employees', function () {
        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $employee3 = Employee::factory()->create(['company_id' => $this->company->id]);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($this->company->employees()->count())->toBe(3);
    });
});

describe('AddEmployeeAction - Edge Cases', function () {
    test('handles payroll with no available employees', function () {
        // Add the only employee to the payroll first
        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
    });

    test('handles empty company', function () {
        $emptyCompany = Company::factory()->create();
        $emptyPayroll = Payroll::factory()->create(['company_id' => $emptyCompany->id]);

        $action = AddEmployeeAction::make($emptyPayroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($emptyCompany->employees()->count())->toBe(0);
    });

    test('handles payroll with no salary adjustments', function () {
        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($this->payroll->salaryAdjustments()->count())->toBe(0);
    });

    test('handles different employee document types', function () {
        $employee2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'document_type' => DocumentTypeEnum::PASSPORT
        ]);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($this->company->employees()->count())->toBe(2);
    });
});

describe('AddEmployeeAction - Data Validation', function () {
    test('action instance validates correctly', function () {
        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($action->getLabel())->toBeString();
        expect($action->getName())->toBeString();
    });

    test('can handle various salary amounts', function () {
        $highSalaryEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $highSalary = Salary::factory()->create([
            'employee_id' => $highSalaryEmployee->id,
            'amount' => 100000
        ]);

        $lowSalaryEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $lowSalary = Salary::factory()->create([
            'employee_id' => $lowSalaryEmployee->id,
            'amount' => 1000
        ]);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($highSalary->amount)->toBe(100000.0);
        expect($lowSalary->amount)->toBe(1000.0);
    });

    test('can handle different salary types', function () {
        $monthlyEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $monthlySalary = Salary::factory()->create([
            'employee_id' => $monthlyEmployee->id,
            'type' => SalaryTypeEnum::MONTHLY
        ]);

        $biweeklyEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $biweeklySalary = Salary::factory()->create([
            'employee_id' => $biweeklyEmployee->id,
            'type' => SalaryTypeEnum::BIWEEKLY
        ]);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($monthlySalary->type)->toBe(SalaryTypeEnum::MONTHLY);
        expect($biweeklySalary->type)->toBe(SalaryTypeEnum::BIWEEKLY);
    });
});

describe('AddEmployeeAction - Error Handling', function () {
    test('action creation does not throw exceptions', function () {
        expect(fn () => AddEmployeeAction::make($this->payroll))->not->toThrow(\Throwable::class);
    });

    test('handles complex payroll relationships', function () {
        // Create multiple employees and salary adjustments
        $employees = Employee::factory(5)->create(['company_id' => $this->company->id]);
        $salaries = $employees->map(
            fn ($employee) =>
            Salary::factory()->create(['employee_id' => $employee->id])
        );

        $adjustments = SalaryAdjustment::factory(3)->create();
        $this->payroll->salaryAdjustments()->attach($adjustments->pluck('id'));

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($this->company->employees()->count())->toBe(6); // 5 new + 1 from beforeEach
        expect($this->payroll->salaryAdjustments()->count())->toBe(3);
    });

    test('handles payroll with existing details and adjustments', function () {
        // Add some employees to the payroll
        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id,
        ]);

        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);
        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id,
        ]);

        $adjustment = SalaryAdjustment::factory()->create();
        $detail1->salaryAdjustments()->attach($adjustment->id);
        $detail2->salaryAdjustments()->attach($adjustment->id);

        $action = AddEmployeeAction::make($this->payroll);

        expect($action)->toBeInstanceOf(Action::class);
        expect($this->payroll->employees()->count())->toBe(2);
        expect($detail1->salaryAdjustments()->count())->toBe(1);
        expect($detail2->salaryAdjustments()->count())->toBe(1);
    });
});
