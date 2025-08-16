<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\QueryBuilders\PayrollDetailBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
    $this->payroll = Payroll::factory()->create(['company_id' => $this->company->id]);
});

describe('PayrollDetail Model - Basic Functionality', function () {
    test('can create payroll detail with required fields', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($detail->payroll_id)->toBe($this->payroll->id);
        expect($detail->employee_id)->toBe($this->employee->id);
        expect($detail->salary_id)->toBe($this->salary->id);
    });

    test('has correct fillable attributes', function () {
        $detail = new PayrollDetail();
        $fillable = $detail->getFillable();

        expect($fillable)->toContain('payroll_id');
        expect($fillable)->toContain('employee_id');
        expect($fillable)->toContain('salary_id');
    });

    test('uses custom query builder', function () {
        $query = PayrollDetail::query();

        expect($query)->toBeInstanceOf(PayrollDetailBuilder::class);
    });
});

describe('PayrollDetail Model - Relationships', function () {
    test('belongs to payroll', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($detail->payroll)->toBeInstanceOf(Payroll::class);
        expect($detail->payroll->id)->toBe($this->payroll->id);
    });

    test('belongs to employee', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($detail->employee)->toBeInstanceOf(Employee::class);
        expect($detail->employee->id)->toBe($this->employee->id);
    });

    test('belongs to salary', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($detail->salary)->toBeInstanceOf(Salary::class);
        expect($detail->salary->id)->toBe($this->salary->id);
    });

    test('belongs to many salary adjustments', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment1 = SalaryAdjustment::factory()->create();
        $adjustment2 = SalaryAdjustment::factory()->create();

        $detail->salaryAdjustments()->attach([
            $adjustment1->id => ['custom_value' => 100],
            $adjustment2->id => ['custom_value' => 200]
        ]);

        expect($detail->salaryAdjustments)->toHaveCount(2);
        expect($detail->salaryAdjustments->pluck('id'))->toContain($adjustment1->id);
        expect($detail->salaryAdjustments->pluck('id'))->toContain($adjustment2->id);
    });
});

describe('PayrollDetail Model - Pivot Data', function () {
    test('stores custom value in salary adjustment pivot', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create([
            'requires_custom_value' => true
        ]);

        $detail->salaryAdjustments()->attach($adjustment->id, ['custom_value' => 150.50]);

        $pivotData = $detail->salaryAdjustments()->first()->detailSalaryAdjustmentValue;
        expect($pivotData->custom_value)->toBe(150.5);
    });

    test('handles null custom value in pivot', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create();

        $detail->salaryAdjustments()->attach($adjustment->id, ['custom_value' => null]);

        $pivotData = $detail->salaryAdjustments()->first()->detailSalaryAdjustmentValue;
        expect($pivotData->custom_value)->toBeNull();
    });

    test('handles zero custom value in pivot', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create();

        $detail->salaryAdjustments()->attach($adjustment->id, ['custom_value' => 0]);

        $pivotData = $detail->salaryAdjustments()->first()->detailSalaryAdjustmentValue;
        expect($pivotData->custom_value)->toBe(0.0);
    });

    test('handles negative custom value in pivot', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create();

        $detail->salaryAdjustments()->attach($adjustment->id, ['custom_value' => -75.25]);

        $pivotData = $detail->salaryAdjustments()->first()->detailSalaryAdjustmentValue;
        expect($pivotData->custom_value)->toBe(-75.25);
    });
});

describe('PayrollDetail Model - Edge Cases', function () {
    test('handles payroll detail with no salary adjustments', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($detail->salaryAdjustments)->toBeEmpty();
    });

    test('handles multiple payroll details for same employee in different payrolls', function () {
        $payroll2 = Payroll::factory()->create(['company_id' => $this->company->id]);

        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $payroll2->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($detail1->employee->id)->toBe($detail2->employee->id);
        expect($detail1->payroll->id)->not->toBe($detail2->payroll->id);
    });

    test('handles payroll detail with different salary for same employee', function () {
        $newSalary = Salary::factory()->create(['employee_id' => $this->employee->id]);

        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $newSalary->id
        ]);

        expect($detail->salary->id)->toBe($newSalary->id);
        expect($detail->salary->id)->not->toBe($this->salary->id);
    });
});

describe('PayrollDetail Model - Constraints and Validation', function () {
    test('allows multiple payroll details for same employee in same payroll', function () {
        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        // This should work - no unique constraint
        $secondDetail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($secondDetail)->toBeInstanceOf(PayrollDetail::class);
        expect(PayrollDetail::count())->toBe(2);
    });

    test('throws exception for non-existent payroll', function () {
        expect(fn () => PayrollDetail::factory()->create([
            'payroll_id' => 99999,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]))->toThrow(\PDOException::class);
    });

    test('throws exception for non-existent employee', function () {
        expect(fn () => PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => 99999,
            'salary_id' => $this->salary->id
        ]))->toThrow(\PDOException::class);
    });

    test('throws exception for non-existent salary', function () {
        expect(fn () => PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => 99999
        ]))->toThrow(\PDOException::class);
    });
});

describe('PayrollDetail Model - Data Integrity', function () {
    test('maintains referential integrity when deleting related models', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create();
        $detail->salaryAdjustments()->attach($adjustment->id);

        expect(PayrollDetail::count())->toBe(1);
        expect($detail->salaryAdjustments()->count())->toBe(1);

        $adjustmentId = $adjustment->id;

        // Delete the payroll detail
        $detail->delete();

        expect(PayrollDetail::count())->toBe(0);
        expect(SalaryAdjustment::find($adjustmentId))->not->toBeNull(); // Adjustment should still exist
    });

    test('handles cascading deletion from payroll', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect(PayrollDetail::count())->toBe(1);

        // This should cascade delete the payroll detail
        $this->payroll->delete();

        expect(PayrollDetail::count())->toBe(0);
    });
});

describe('PayrollDetail Model - Factory Integration', function () {
    test('factory creates valid payroll detail', function () {
        $detail = PayrollDetail::factory()->create();

        expect($detail->payroll)->toBeInstanceOf(Payroll::class);
        expect($detail->employee)->toBeInstanceOf(Employee::class);
        expect($detail->salary)->toBeInstanceOf(Salary::class);
    });

    test('factory respects provided attributes', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($detail->payroll_id)->toBe($this->payroll->id);
        expect($detail->employee_id)->toBe($this->employee->id);
        expect($detail->salary_id)->toBe($this->salary->id);
    });
});
