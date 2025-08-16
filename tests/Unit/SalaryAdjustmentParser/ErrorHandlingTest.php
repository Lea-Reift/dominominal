<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Support\SalaryAdjustmentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create();
    $this->salary = Salary::factory()->create([
        'employee_id' => $this->employee->id,
        'amount' => 50000.00,
    ]);
    $this->payroll = Payroll::factory()->create([
        'company_id' => $this->employee->company_id,
    ]);
    $this->payrollDetail = PayrollDetail::factory()->create([
        'employee_id' => $this->employee->id,
        'payroll_id' => $this->payroll->id,
        'salary_id' => $this->salary->id,
    ]);
});

describe('SalaryAdjustmentParser - Error Handling', function () {
    test('throws exception for unresolvable variable dependencies', function () {
        $adj1 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'A',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'UNKNOWN_VAR * 2',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adj1);

        expect(fn () => new SalaryAdjustmentParser($this->payrollDetail))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('throws exception for circular dependencies', function () {
        $adj1 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'A',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'B + 1',
            'requires_custom_value' => false,
        ]);

        $adj2 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'B',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'A + 1',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach([$adj1->id, $adj2->id]);

        expect(fn () => new SalaryAdjustmentParser($this->payrollDetail))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('handles malformed formula gracefully', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'MALFORMED',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'UNKNOWN_VAR + + 100', // Invalid syntax with unknown variable
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        expect(fn () => new SalaryAdjustmentParser($this->payrollDetail))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('throws syntax error for invalid mathematical expressions', function () {
        $baseAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'BASE_VALUE_TEST',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '100',
            'requires_custom_value' => false,
        ]);

        $malformedAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'SYNTAX_ERROR_TEST',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'BASE_VALUE_TEST + +', // Invalid syntax
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach([$baseAdjustment->id, $malformedAdjustment->id]);

        expect(fn () => new SalaryAdjustmentParser($this->payrollDetail))
            ->toThrow(\Symfony\Component\ExpressionLanguage\SyntaxError::class);
    });

    test('handles non-numeric string values as dependency error', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'NON_NUMERIC_TEST',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => 'not a number',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        // Non-numeric strings cause dependency resolution failures
        expect(fn () => new SalaryAdjustmentParser($this->payrollDetail))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('handles mixed numeric and non-numeric characters as dependency error', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'MIXED_VALUE_TEST',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '123abc456',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        // Mixed alphanumeric strings cause dependency resolution failures
        expect(fn () => new SalaryAdjustmentParser($this->payrollDetail))
            ->toThrow(\InvalidArgumentException::class);
    });
});
