<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Support\ValueObjects\PayrollDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
});

describe('Payroll Model - Basic Functionality', function () {
    test('can create payroll with required fields', function () {
        $payroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY,
            'period' => now()
        ]);

        expect($payroll->company_id)->toBe($this->company->id);
        expect($payroll->type)->toBe(SalaryTypeEnum::MONTHLY);
        expect($payroll->period)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    test('has correct fillable attributes', function () {
        $payroll = new Payroll();
        $fillable = $payroll->getFillable();

        expect($fillable)->toContain('company_id');
        expect($fillable)->toContain('type');
        expect($fillable)->toContain('period');
        expect($fillable)->toContain('monthly_payroll_id');
    });

    test('casts attributes correctly', function () {
        $payroll = Payroll::factory()->create([
            'type' => SalaryTypeEnum::BIWEEKLY,
            'period' => '2024-01-15'
        ]);

        expect($payroll->type)->toBeInstanceOf(SalaryTypeEnum::class);
        expect($payroll->period)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    test('has payroll display attribute', function () {
        $payroll = Payroll::factory()->create();

        expect($payroll->display)->toBeInstanceOf(PayrollDisplay::class);
    });
});

describe('Payroll Model - Relationships', function () {
    test('belongs to company', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        expect($payroll->company)->toBeInstanceOf(Company::class);
        expect($payroll->company->id)->toBe($this->company->id);
    });

    test('belongs to monthly payroll', function () {
        $monthlyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY
        ]);

        $biweeklyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::BIWEEKLY,
            'monthly_payroll_id' => $monthlyPayroll->id
        ]);

        expect($biweeklyPayroll->monthlyPayroll)->toBeInstanceOf(Payroll::class);
        expect($biweeklyPayroll->monthlyPayroll->id)->toBe($monthlyPayroll->id);
    });

    test('has many biweekly payrolls', function () {
        $monthlyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY
        ]);

        $biweekly1 = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::BIWEEKLY,
            'monthly_payroll_id' => $monthlyPayroll->id
        ]);

        $biweekly2 = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::BIWEEKLY,
            'monthly_payroll_id' => $monthlyPayroll->id
        ]);

        expect($monthlyPayroll->biweeklyPayrolls)->toHaveCount(2);
        expect($monthlyPayroll->biweeklyPayrolls->pluck('id'))->toContain($biweekly1->id);
        expect($monthlyPayroll->biweeklyPayrolls->pluck('id'))->toContain($biweekly2->id);
    });

    test('has many employees through details', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($payroll->employees)->toHaveCount(1);
        expect($payroll->employees->first()->id)->toBe($this->employee->id);
    });

    test('has many payroll details', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $detail1 = PayrollDetail::factory()->create(['payroll_id' => $payroll->id]);
        $detail2 = PayrollDetail::factory()->create(['payroll_id' => $payroll->id]);

        expect($payroll->details)->toHaveCount(2);
        expect($payroll->details->pluck('id'))->toContain($detail1->id);
        expect($payroll->details->pluck('id'))->toContain($detail2->id);
    });

    test('has many to many salary adjustments', function () {
        $payroll = Payroll::factory()->create();
        $adjustment1 = SalaryAdjustment::factory()->create();
        $adjustment2 = SalaryAdjustment::factory()->create();

        $payroll->salaryAdjustments()->attach([$adjustment1->id, $adjustment2->id]);

        expect($payroll->salaryAdjustments)->toHaveCount(2);
        expect($payroll->salaryAdjustments->pluck('id'))->toContain($adjustment1->id);
        expect($payroll->salaryAdjustments->pluck('id'))->toContain($adjustment2->id);
    });

    test('filters editable salary adjustments', function () {
        $payroll = Payroll::factory()->create();

        $editableAdjustment = SalaryAdjustment::factory()->create(['requires_custom_value' => true]);
        $nonEditableAdjustment = SalaryAdjustment::factory()->create(['requires_custom_value' => false]);

        $payroll->salaryAdjustments()->attach([
            $editableAdjustment->id,
            $nonEditableAdjustment->id
        ]);

        expect($payroll->editableSalaryAdjustments)->toHaveCount(1);
        expect($payroll->editableSalaryAdjustments->first()->id)->toBe($editableAdjustment->id);
    });

    test('filters income salary adjustments', function () {
        $payroll = Payroll::factory()->create();

        $income = SalaryAdjustment::factory()->create(['type' => SalaryAdjustmentTypeEnum::INCOME]);
        $deduction = SalaryAdjustment::factory()->create(['type' => SalaryAdjustmentTypeEnum::DEDUCTION]);

        $payroll->salaryAdjustments()->attach([$income->id, $deduction->id]);

        expect($payroll->incomes)->toHaveCount(1);
        expect($payroll->incomes->first()->id)->toBe($income->id);
    });

    test('filters deduction salary adjustments', function () {
        $payroll = Payroll::factory()->create();

        $income = SalaryAdjustment::factory()->create(['type' => SalaryAdjustmentTypeEnum::INCOME]);
        $deduction = SalaryAdjustment::factory()->create(['type' => SalaryAdjustmentTypeEnum::DEDUCTION]);

        $payroll->salaryAdjustments()->attach([$income->id, $deduction->id]);

        expect($payroll->deductions)->toHaveCount(1);
        expect($payroll->deductions->first()->id)->toBe($deduction->id);
    });
});

describe('Payroll Model - Boot Methods', function () {
    test('sets period to end of month for monthly payrolls', function () {
        $payroll = Payroll::factory()->create([
            'type' => SalaryTypeEnum::MONTHLY,
            'period' => '2024-01-15'
        ]);

        expect($payroll->fresh()->period->day)->toBe(30);
    });

    test('sets period to 28th for february monthly payrolls', function () {
        $payroll = Payroll::factory()->create([
            'type' => SalaryTypeEnum::MONTHLY,
            'period' => '2024-02-15'
        ]);

        expect($payroll->fresh()->period->day)->toBe(28);
    });

    test('does not modify period for biweekly payrolls', function () {
        $originalDate = '2024-01-15';
        $payroll = Payroll::factory()->create([
            'type' => SalaryTypeEnum::BIWEEKLY,
            'period' => $originalDate
        ]);

        expect($payroll->fresh()->period->day)->toBe(15);
    });

    test('deleting payroll cascades to details and adjustments', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create();
        $payroll->salaryAdjustments()->attach($adjustment->id);
        $detail->salaryAdjustments()->attach($adjustment->id);

        expect(PayrollDetail::count())->toBe(1);
        expect($payroll->salaryAdjustments()->count())->toBe(1);

        $adjustmentId = $adjustment->id;

        $payroll->delete();

        expect(PayrollDetail::count())->toBe(0);
        expect(SalaryAdjustment::find($adjustmentId))->not->toBeNull(); // Adjustment should still exist
    });
});

describe('Payroll Model - Edge Cases', function () {
    test('handles null monthly payroll id', function () {
        $payroll = Payroll::factory()->create(['monthly_payroll_id' => null]);

        expect($payroll->monthlyPayroll)->toBeNull();
    });

    test('handles payroll with no employees', function () {
        $payroll = Payroll::factory()->create();

        expect($payroll->employees)->toBeEmpty();
        expect($payroll->details)->toBeEmpty();
    });

    test('handles payroll with no salary adjustments', function () {
        $payroll = Payroll::factory()->create();

        expect($payroll->salaryAdjustments)->toBeEmpty();
        expect($payroll->editableSalaryAdjustments)->toBeEmpty();
        expect($payroll->incomes)->toBeEmpty();
        expect($payroll->deductions)->toBeEmpty();
    });

    test('handles payroll period in different years', function () {
        $payroll = Payroll::factory()->create([
            'type' => SalaryTypeEnum::MONTHLY,
            'period' => '2025-12-15'
        ]);

        expect($payroll->fresh()->period->year)->toBe(2025);
        expect($payroll->fresh()->period->month)->toBe(12);
        expect($payroll->fresh()->period->day)->toBe(30);
    });
});

describe('Payroll Model - Error Handling', function () {
    test('throws exception for invalid enum type', function () {
        expect(fn () => Payroll::factory()->create(['type' => 'invalid_type']))
            ->toThrow(\TypeError::class);
    });

    test('throws exception for invalid date format', function () {
        expect(fn () => Payroll::factory()->create(['period' => 'invalid_date']))
            ->toThrow(\Carbon\Exceptions\InvalidFormatException::class);
    });

    test('throws exception for non-existent company', function () {
        expect(fn () => Payroll::factory()->create(['company_id' => 99999]))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('throws exception for non-existent monthly payroll', function () {
        expect(fn () => Payroll::factory()->create(['monthly_payroll_id' => 99999]))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });
});
