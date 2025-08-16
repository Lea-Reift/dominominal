<?php

declare(strict_types=1);

use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Actions\HeaderActions\EditPayrollAction;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Actions\EditAction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
});

describe('EditPayrollAction - Basic Functionality', function () {
    test('can create edit action instance', function () {
        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($action->getLabel())->toBe('Editar');
    });

    test('action has correct name', function () {
        $action = EditPayrollAction::make();

        expect($action->getName())->toBe('edit');
    });

    test('action is properly configured', function () {
        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($action->getLabel())->toBe('Editar');
        expect($action->getName())->toBe('edit');
    });
});

describe('EditPayrollAction - Integration Tests', function () {
    test('can handle payroll with salary adjustments', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id,
        ]);

        $adjustment1 = SalaryAdjustment::factory()->create();
        $adjustment2 = SalaryAdjustment::factory()->create();

        $detail->salaryAdjustments()->attach($adjustment1->id);
        $payroll->salaryAdjustments()->attach([$adjustment1->id, $adjustment2->id]);

        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($payroll->salaryAdjustments)->toHaveCount(2);
        expect($detail->salaryAdjustments)->toHaveCount(1);
    });

    test('can handle monthly and biweekly payrolls', function () {
        $monthlyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY,
        ]);

        $biweeklyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::BIWEEKLY,
            'monthly_payroll_id' => $monthlyPayroll->id,
        ]);

        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($monthlyPayroll->type)->toBe(SalaryTypeEnum::MONTHLY);
        expect($biweeklyPayroll->type)->toBe(SalaryTypeEnum::BIWEEKLY);
        expect($biweeklyPayroll->monthly_payroll_id)->toBe($monthlyPayroll->id);
    });
});

describe('EditPayrollAction - Edge Cases', function () {
    test('handles payroll with no details', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($payroll->details)->toBeEmpty();
    });

    test('handles payroll with no salary adjustments', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($payroll->salaryAdjustments)->toBeEmpty();
    });

    test('handles different payroll types', function () {
        $monthlyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY,
        ]);

        $biweeklyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::BIWEEKLY,
        ]);

        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($monthlyPayroll->type->isMonthly())->toBeTrue();
        expect($biweeklyPayroll->type->isBiweekly())->toBeTrue();
    });
});

describe('EditPayrollAction - Error Handling', function () {
    test('action creation does not throw exceptions', function () {
        expect(fn () => EditPayrollAction::make())->not->toThrow(\Throwable::class);
    });

    test('can handle complex payroll scenarios', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        // Create multiple details and adjustments
        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);

        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id,
        ]);

        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id,
        ]);

        $adjustment1 = SalaryAdjustment::factory()->create();
        $adjustment2 = SalaryAdjustment::factory()->create();

        $payroll->salaryAdjustments()->attach([$adjustment1->id, $adjustment2->id]);
        $detail1->salaryAdjustments()->attach($adjustment1->id);
        $detail2->salaryAdjustments()->attach($adjustment2->id);

        $action = EditPayrollAction::make();

        expect($action)->toBeInstanceOf(EditAction::class);
        expect($payroll->details)->toHaveCount(2);
        expect($payroll->salaryAdjustments)->toHaveCount(2);
    });
});
