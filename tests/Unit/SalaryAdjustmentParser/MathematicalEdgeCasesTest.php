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

describe('SalaryAdjustmentParser - Mathematical Edge Cases', function () {
    test('handles zero salary amount', function () {
        $this->salary->update(['amount' => 0]);
        
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'BONUS',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'value' => '10',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('BONUS'))->toBe(0.0); // 10% of 0
    });

    test('handles negative salary adjustments', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'DEDUCTION',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '-1000',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('DEDUCTION'))->toBe(-1000.0);
    });

    test('handles very large numbers', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'LARGE_BONUS',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '999999999.99',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('LARGE_BONUS'))->toBe(999999999.99);
    });

    test('handles percentage over 100%', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'HUGE_BONUS',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'value' => '150',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('HUGE_BONUS'))->toBe(75000.0); // 150% of 50000
    });

    test('handles negative percentage', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'NEGATIVE_PERCENTAGE',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'value' => '-10',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('NEGATIVE_PERCENTAGE'))->toBe(-5000.0); // -10% of 50000
    });

    test('handles division by zero in formula', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'DIVISION_TEST',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'DETALLE.salary.amount / 0',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        expect(fn () => new SalaryAdjustmentParser($this->payrollDetail))
            ->toThrow(\DivisionByZeroError::class);
    });

    test('handles complex mathematical expressions', function () {
        $baseAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'BASE',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1000',
            'requires_custom_value' => false,
        ]);

        $complexAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'COMPLEX_CALC',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => '(BASE * 2) + (DETALLE.salary.amount * 0.05) - 500',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach([$baseAdjustment->id, $complexAdjustment->id]);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        // (1000 * 2) + (50000 * 0.05) - 500 = 2000 + 2500 - 500 = 4000
        expect($variables->get('COMPLEX_CALC'))->toBe(4000.0);
    });

    test('handles decimal values correctly', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'DECIMAL_VALUE',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1234.56789',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('DECIMAL_VALUE'))->toBe(1234.56789);
    });

    test('handles scientific notation', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'SCIENTIFIC',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1.5e3', // 1500
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('SCIENTIFIC'))->toBe(1500.0);
    });

    test('handles extremely small decimal values', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'TINY_VALUE',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '0.00001',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('TINY_VALUE'))->toBe(0.00001);
    });

    test('handles array variables with mixed formula types', function () {
        $customVars = [
            'MIXED_ARRAY' => [
                'fixed' => '1000',
                'percentage' => 'DETALLE.salary.amount * 0.1',
                'complex' => '(DETALLE.salary.amount / 12) + 500',
            ]
        ];

        $parser = new SalaryAdjustmentParser($this->payrollDetail, $customVars);
        $variables = $parser->variables();

        expect($variables->get('MIXED_ARRAY'))->toBe([
            'fixed' => 1000.0,
            'percentage' => 5000.0,
            'complex' => 4666.666666666667, // (50000/12) + 500
        ]);
    });

    test('handles empty string values in adjustments', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'EMPTY_VALUE',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('EMPTY_VALUE'))->toBe(0.0);
    });

    test('handles whitespace-only values', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'WHITESPACE',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '   ',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('WHITESPACE'))->toBe(0.0);
    });

    test('handles special characters in parser alias', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'SPECIAL_CHARS_123',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '500',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('SPECIAL_CHARS_123'))->toBe(500.0);
    });
});