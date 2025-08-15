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

describe('SalaryAdjustmentParser - Dependency Resolution', function () {
    test('sorts variables with dependencies correctly', function () {
        $adj1 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'A',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '100',
            'requires_custom_value' => false,
        ]);

        $adj2 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'B',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'A * 2',
            'requires_custom_value' => false,
        ]);

        $adj3 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'C',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'B + A',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach([$adj1->id, $adj2->id, $adj3->id]);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('A'))->toBe(100.0);
        expect($variables->get('B'))->toBe(200.0);
        expect($variables->get('C'))->toBe(300.0);
    });

    test('handles deeply nested formula dependencies', function () {
        $adj1 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'LEVEL1',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '100',
            'requires_custom_value' => false,
        ]);

        $adj2 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'LEVEL2',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'LEVEL1 * 2',
            'requires_custom_value' => false,
        ]);

        $adj3 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'LEVEL3',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'LEVEL2 + LEVEL1',
            'requires_custom_value' => false,
        ]);

        $adj4 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'LEVEL4',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'LEVEL3 * 0.5',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach([$adj1->id, $adj2->id, $adj3->id, $adj4->id]);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('LEVEL1'))->toBe(100.0);
        expect($variables->get('LEVEL2'))->toBe(200.0);
        expect($variables->get('LEVEL3'))->toBe(300.0);
        expect($variables->get('LEVEL4'))->toBe(150.0);
    });

    test('parses formula value salary adjustments correctly', function () {
        $baseAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'BASE',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1000',
            'requires_custom_value' => false,
        ]);

        $formulaAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'CALCULATED',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'BASE * 2',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach([$baseAdjustment->id, $formulaAdjustment->id]);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('BASE'))->toBe(1000.0);
        expect($variables->get('CALCULATED'))->toBe(2000.0);
    });

    test('resolves dependencies with DETALLE references', function () {
        $customVars = [
            'SALARY_HALF' => 'DETALLE.salary.amount / 2',
            'SALARY_QUARTER' => 'SALARY_HALF / 2',
        ];

        $parser = new SalaryAdjustmentParser($this->payrollDetail, $customVars);
        $variables = $parser->variables();

        expect($variables->get('SALARY_HALF'))->toBe(25000.0);
        expect($variables->get('SALARY_QUARTER'))->toBe(12500.0);
    });

    test('handles mixed dependency types', function () {
        $absoluteAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'FIXED_AMOUNT',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1000',
            'requires_custom_value' => false,
        ]);

        $percentageAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'PERCENTAGE_AMOUNT',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'value' => '5',
            'requires_custom_value' => false,
        ]);

        $formulaAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'COMBINED',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'FIXED_AMOUNT + PERCENTAGE_AMOUNT',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach([
            $absoluteAdjustment->id, 
            $percentageAdjustment->id, 
            $formulaAdjustment->id
        ]);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('FIXED_AMOUNT'))->toBe(1000.0);
        expect($variables->get('PERCENTAGE_AMOUNT'))->toBe(2500.0); // 5% of 50000
        expect($variables->get('COMBINED'))->toBe(3500.0); // 1000 + 2500
    });

    test('handles custom variables with formula dependencies', function () {
        $baseAdjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'BASE_SALARY_BONUS',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '2000',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($baseAdjustment);

        $customVars = [
            'CUSTOM_CALC' => 'BASE_SALARY_BONUS * 1.5',
            'FINAL_AMOUNT' => 'CUSTOM_CALC + 1000', // Simplified to avoid complex dependency
        ];

        $parser = new SalaryAdjustmentParser($this->payrollDetail, $customVars);
        $variables = $parser->variables();

        expect($variables->get('BASE_SALARY_BONUS'))->toBe(2000.0);
        expect($variables->get('CUSTOM_CALC'))->toBe(3000.0); // 2000 * 1.5
        expect($variables->get('FINAL_AMOUNT'))->toBe(4000.0); // 3000 + 1000
    });
});