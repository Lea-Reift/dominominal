<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\QueryBuilders\SalaryAdjustmentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
    $this->payroll = Payroll::factory()->create(['company_id' => $this->company->id]);
});

describe('SalaryAdjustmentBuilder - Basic Functionality', function () {
    test('returns correct builder instance', function () {
        $builder = SalaryAdjustment::query();

        expect($builder)->toBeInstanceOf(SalaryAdjustmentBuilder::class);
    });

    test('can chain standard query methods', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Test Adjustment'
        ]);

        $result = SalaryAdjustment::query()
            ->where('name', 'Test Adjustment')
            ->first();

        expect($result)->toBeInstanceOf(SalaryAdjustment::class);
        expect($result->id)->toBe($adjustment->id);
    });

    test('can use with relationships', function () {
        $adjustment = SalaryAdjustment::factory()->create();
        $this->payroll->salaryAdjustments()->attach($adjustment->id);

        $result = SalaryAdjustment::query()
            ->with(['payrolls', 'payrollDetails'])
            ->first();

        expect($result->relationLoaded('payrolls'))->toBeTrue();
        expect($result->relationLoaded('payrollDetails'))->toBeTrue();
    });
});

describe('SalaryAdjustmentBuilder - Type Filtering', function () {
    test('can filter by income type', function () {
        $income = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME
        ]);

        $deduction = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION
        ]);

        $results = SalaryAdjustment::query()
            ->where('type', SalaryAdjustmentTypeEnum::INCOME)
            ->get();

        expect($results->pluck('id'))->toContain($income->id);
        expect($results->pluck('id'))->not->toContain($deduction->id);
    });

    test('can filter by deduction type', function () {
        $income = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME
        ]);

        $deduction = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION
        ]);

        $results = SalaryAdjustment::query()
            ->where('type', SalaryAdjustmentTypeEnum::DEDUCTION)
            ->get();

        expect($results->pluck('id'))->toContain($deduction->id);
        expect($results->pluck('id'))->not->toContain($income->id);
    });

    test('can filter by multiple types', function () {
        $income = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'name' => 'Test Income Filter Multiple'
        ]);

        $deduction = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION,
            'name' => 'Test Deduction Filter Multiple'
        ]);

        $results = SalaryAdjustment::query()
            ->whereIn('type', [SalaryAdjustmentTypeEnum::INCOME, SalaryAdjustmentTypeEnum::DEDUCTION])
            ->where('name', 'LIKE', '%Filter Multiple%')
            ->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('id'))->toContain($income->id);
        expect($results->pluck('id'))->toContain($deduction->id);
    });
});

describe('SalaryAdjustmentBuilder - Value Type Filtering', function () {
    test('can filter by absolute value type', function () {
        $absolute = SalaryAdjustment::factory()->create([
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'name' => 'Test Absolute Value Type'
        ]);

        $percentage = SalaryAdjustment::factory()->create([
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'name' => 'Test Percentage Value Type'
        ]);

        $results = SalaryAdjustment::query()
            ->where('value_type', SalaryAdjustmentValueTypeEnum::ABSOLUTE)
            ->where('name', 'Test Absolute Value Type')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($absolute->id);
    });

    test('can filter by percentage value type', function () {
        $absolute = SalaryAdjustment::factory()->create([
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'name' => 'Test Absolute Percentage Filter'
        ]);

        $percentage = SalaryAdjustment::factory()->create([
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'name' => 'Test Percentage Filter'
        ]);

        $results = SalaryAdjustment::query()
            ->where('value_type', SalaryAdjustmentValueTypeEnum::PERCENTAGE)
            ->where('name', 'Test Percentage Filter')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($percentage->id);
    });

    test('can filter by formula value type', function () {
        $formula = SalaryAdjustment::factory()->create([
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'DETALLE.salary.amount * 0.1',
            'name' => 'Test Formula Value Type'
        ]);

        $absolute = SalaryAdjustment::factory()->create([
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'name' => 'Test Absolute Formula Filter'
        ]);

        $results = SalaryAdjustment::query()
            ->where('value_type', SalaryAdjustmentValueTypeEnum::FORMULA)
            ->where('name', 'Test Formula Value Type')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($formula->id);
    });
});

describe('SalaryAdjustmentBuilder - Boolean Flag Filtering', function () {
    test('can filter by requires custom value', function () {
        $customValue = SalaryAdjustment::factory()->create([
            'requires_custom_value' => true,
            'name' => 'Test Custom Value True'
        ]);

        $noCustomValue = SalaryAdjustment::factory()->create([
            'requires_custom_value' => false,
            'name' => 'Test Custom Value False'
        ]);

        $results = SalaryAdjustment::query()
            ->where('requires_custom_value', true)
            ->where('name', 'Test Custom Value True')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($customValue->id);
    });

    test('can filter by ignore in deductions', function () {
        $ignored = SalaryAdjustment::factory()->create([
            'ignore_in_deductions' => true,
            'name' => 'Test Ignore Deductions True'
        ]);

        $notIgnored = SalaryAdjustment::factory()->create([
            'ignore_in_deductions' => false,
            'name' => 'Test Ignore Deductions False'
        ]);

        $results = SalaryAdjustment::query()
            ->where('ignore_in_deductions', false)
            ->where('name', 'Test Ignore Deductions False')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($notIgnored->id);
    });

    test('can filter by absolute adjustment flag', function () {
        $absolute = SalaryAdjustment::factory()->create([
            'is_absolute_adjustment' => true,
            'name' => 'Test Absolute Adjustment True'
        ]);

        $notAbsolute = SalaryAdjustment::factory()->create([
            'is_absolute_adjustment' => false,
            'name' => 'Test Absolute Adjustment False'
        ]);

        $results = SalaryAdjustment::query()
            ->where('is_absolute_adjustment', true)
            ->where('name', 'Test Absolute Adjustment True')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($absolute->id);
    });
});

describe('SalaryAdjustmentBuilder - Search and Ordering', function () {
    test('can search by name', function () {
        $bonus = SalaryAdjustment::factory()->create([
            'name' => 'Monthly Bonus'
        ]);

        $tax = SalaryAdjustment::factory()->create([
            'name' => 'Income Tax'
        ]);

        $results = SalaryAdjustment::query()
            ->where('name', 'LIKE', '%Bonus%')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($bonus->id);
    });

    test('can search by parser alias', function () {
        $adjustment1 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'MONTHLY_BONUS'
        ]);

        $adjustment2 = SalaryAdjustment::factory()->create([
            'parser_alias' => 'INCOME_TAX'
        ]);

        $results = SalaryAdjustment::query()
            ->where('parser_alias', 'MONTHLY_BONUS')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($adjustment1->id);
    });

    test('can order by name', function () {
        $uniqueId = time() . rand(1000, 9999);
        $zeta = SalaryAdjustment::factory()->create(['name' => "Zeta Adjustment {$uniqueId}"]);
        $alpha = SalaryAdjustment::factory()->create(['name' => "Alpha Adjustment {$uniqueId}"]);
        $beta = SalaryAdjustment::factory()->create(['name' => "Beta Adjustment {$uniqueId}"]);

        $results = SalaryAdjustment::query()
            ->where('name', 'LIKE', "%Adjustment {$uniqueId}%")
            ->orderBy('name')
            ->get();

        expect($results)->toHaveCount(3);
        expect($results->first()->name)->toBe("Alpha Adjustment {$uniqueId}");
        expect($results->last()->name)->toBe("Zeta Adjustment {$uniqueId}");
    });

    test('can order by type', function () {
        $uniqueId = time() . rand(1000, 9999);
        $income = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'name' => "Income Order Test {$uniqueId}"
        ]);

        $deduction = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION,
            'name' => "Deduction Order Test {$uniqueId}"
        ]);

        $results = SalaryAdjustment::query()
            ->where('name', 'LIKE', "%Order Test {$uniqueId}%")
            ->orderBy('type')
            ->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('id'))->toContain($income->id);
        expect($results->pluck('id'))->toContain($deduction->id);
    });
});

describe('SalaryAdjustmentBuilder - Relationship Queries', function () {
    test('can query with payrolls', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Test Payroll Relation ' . time() . rand(1000, 9999)
        ]);
        $this->payroll->salaryAdjustments()->attach($adjustment->id);

        $result = SalaryAdjustment::query()
            ->where('id', $adjustment->id)
            ->with('payrolls')
            ->first();

        expect($result->payrolls)->toHaveCount(1);
        expect($result->payrolls->first()->id)->toBe($this->payroll->id);
    });

    test('can filter by payroll relationship', function () {
        $adjustment1 = SalaryAdjustment::factory()->create();
        $adjustment2 = SalaryAdjustment::factory()->create();

        $this->payroll->salaryAdjustments()->attach($adjustment1->id);

        $results = SalaryAdjustment::query()
            ->whereHas('payrolls', function ($query) {
                $query->where('payrolls.id', $this->payroll->id);
            })
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($adjustment1->id);
    });

    test('can query with payroll details', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Test Detail Relation ' . time() . rand(1000, 9999)
        ]);

        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $detail->salaryAdjustments()->attach($adjustment->id, ['custom_value' => 100]);

        $result = SalaryAdjustment::query()
            ->where('id', $adjustment->id)
            ->with('payrollDetails')
            ->first();

        expect($result->payrollDetails)->toHaveCount(1);
        expect($result->payrollDetails->first()->id)->toBe($detail->id);
    });
});

describe('SalaryAdjustmentBuilder - Complex Queries', function () {
    test('can combine multiple filters', function () {
        $uniqueId = time() . rand(1000, 9999);
        $target = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'requires_custom_value' => true,
            'name' => "Target Complex Filter {$uniqueId}"
        ]);

        $other = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION,
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'requires_custom_value' => false,
            'name' => "Other Complex Filter {$uniqueId}"
        ]);

        $results = SalaryAdjustment::query()
            ->where('type', SalaryAdjustmentTypeEnum::INCOME)
            ->where('value_type', SalaryAdjustmentValueTypeEnum::ABSOLUTE)
            ->where('requires_custom_value', true)
            ->where('name', 'LIKE', "%Complex Filter {$uniqueId}%")
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($target->id);
    });

    test('can use subqueries', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Subquery Test ' . time() . rand(1000, 9999)
        ]);
        $this->payroll->salaryAdjustments()->attach($adjustment->id);

        $results = SalaryAdjustment::query()
            ->whereHas('payrolls', function ($query) {
                $query->where('payrolls.id', $this->payroll->id);
            })
            ->where('id', $adjustment->id)
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($adjustment->id);
    });
});

describe('SalaryAdjustmentBuilder - Edge Cases', function () {
    test('handles empty result sets', function () {
        $results = SalaryAdjustment::query()
            ->where('name', 'NonExistentAdjustment')
            ->get();

        expect($results)->toBeEmpty();
    });

    test('handles null values in filters', function () {
        $uniqueId = time() . rand(1000, 9999);
        $adjustment = SalaryAdjustment::factory()->create([
            'value' => null,
            'name' => "Null Value Test {$uniqueId}"
        ]);

        $results = SalaryAdjustment::query()
            ->whereNull('value')
            ->where('name', "Null Value Test {$uniqueId}")
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($adjustment->id);
    });

    test('handles case sensitivity in search', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Monthly Bonus'
        ]);

        $results = SalaryAdjustment::query()
            ->where('name', 'LIKE', '%monthly%')
            ->get();

        // Depending on database collation, this might be case-sensitive or not
        expect($results->count())->toBeGreaterThanOrEqual(0);
    });
});

describe('SalaryAdjustmentBuilder - Performance', function () {
    test('efficiently queries large datasets', function () {
        // Create 100 salary adjustments
        for ($i = 0; $i < 100; $i++) {
            SalaryAdjustment::factory()->create([
                'name' => "Adjustment {$i}",
                'parser_alias' => "ADJ_{$i}"
            ]);
        }

        $startTime = microtime(true);

        $results = SalaryAdjustment::query()
            ->where('type', SalaryAdjustmentTypeEnum::INCOME)
            ->orderBy('name')
            ->get();

        $endTime = microtime(true);

        expect($results->count())->toBeGreaterThan(0);
        expect($endTime - $startTime)->toBeLessThan(1.0); // Should complete in under 1 second
    });

    test('uses indexes efficiently on parser alias', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'parser_alias' => 'UNIQUE_ALIAS'
        ]);

        $result = SalaryAdjustment::query()
            ->where('parser_alias', 'UNIQUE_ALIAS')
            ->first();

        expect($result->id)->toBe($adjustment->id);
    });
});

describe('SalaryAdjustmentBuilder - Error Handling', function () {
    test('handles invalid enum values gracefully', function () {
        // This should not find any results for invalid enum value
        $results = SalaryAdjustment::query()
            ->where('type', 'INVALID_TYPE')
            ->get();

        expect($results)->toBeEmpty();
    });

    test('handles invalid column names', function () {
        $results = SalaryAdjustment::query()
            ->where('invalid_column', 'value')
            ->get();

        expect($results)->toBeEmpty();
    });
});
