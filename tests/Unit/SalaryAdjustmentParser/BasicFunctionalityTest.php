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

describe('SalaryAdjustmentParser - Basic Functionality', function () {
    test('can be instantiated with payroll detail', function () {
        $parser = new SalaryAdjustmentParser($this->payrollDetail);

        expect($parser)->toBeInstanceOf(SalaryAdjustmentParser::class);
    });

    test('can be created using make method', function () {
        $parser = SalaryAdjustmentParser::make($this->payrollDetail);

        expect($parser)->toBeInstanceOf(SalaryAdjustmentParser::class);
    });

    test('can set default variables', function () {
        $defaultVars = ['TEST_VAR' => 100];
        $result = SalaryAdjustmentParser::setDefaultVariables($defaultVars);

        expect($result)->toHaveKey('TEST_VAR');
        expect($result['TEST_VAR'])->toBe(100);
    });

    test('excludes DETALLE from variables output', function () {
        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->has('DETALLE'))->toBeFalse();
    });

    test('handles empty payroll detail with no adjustments', function () {
        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    test('parses absolute value salary adjustments correctly', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'BONUS',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '5000',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('BONUS'))->toBe(5000.0);
    });

    test('parses percentage value salary adjustments correctly', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'COMMISSION',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'value' => '10',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('COMMISSION'))->toBe(5000.0); // 10% of 50000
    });

    test('handles numeric string values correctly', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'NUMERIC_STRING',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1500.50',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('NUMERIC_STRING'))->toBe(1500.5);
    });

    test('handles custom variables correctly', function () {
        $customVars = ['CUSTOM_VAR' => 'DETALLE.salary.amount * 0.1'];

        $parser = new SalaryAdjustmentParser($this->payrollDetail, $customVars);
        $variables = $parser->variables();

        expect($variables->get('CUSTOM_VAR'))->toBe(5000.0); // 10% of salary
    });

    test('can access payroll detail through DETALLE variable in formulas', function () {
        $customVars = ['SALARY_AMOUNT' => 'DETALLE.salary.amount'];

        $parser = new SalaryAdjustmentParser($this->payrollDetail, $customVars);
        $variables = $parser->variables();

        expect($variables->get('SALARY_AMOUNT'))->toBe(50000.0);
    });

    test('merges default variables with adjustment variables', function () {
        SalaryAdjustmentParser::setDefaultVariables(['DEFAULT_VAR' => 999]);

        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'CUSTOM',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '500',
            'requires_custom_value' => false,
        ]);

        $this->payrollDetail->salaryAdjustments()->attach($adjustment);

        $parser = new SalaryAdjustmentParser($this->payrollDetail);
        $variables = $parser->variables();

        expect($variables->get('DEFAULT_VAR'))->toBe(999);
        expect($variables->get('CUSTOM'))->toBe(500.0);
    });

    test('custom variables override default variables', function () {
        SalaryAdjustmentParser::setDefaultVariables(['OVERRIDE_VAR' => 100]);

        $customVars = ['OVERRIDE_VAR' => 200];

        $parser = new SalaryAdjustmentParser($this->payrollDetail, $customVars);
        $variables = $parser->variables();

        expect($variables->get('OVERRIDE_VAR'))->toBe(200);
    });
});
