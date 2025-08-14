<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Models\User;
use App\Modules\Payroll\Models\SalaryAdjustment;

use function Pest\Laravel\actingAs;

describe('SalaryAdjustment Query Builder', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('uses custom query builder', function () {
        $builder = SalaryAdjustment::query();

        expect($builder)->toBeInstanceOf(\App\Modules\Payroll\QueryBuilders\SalaryAdjustmentBuilder::class);
    });

    test('can filter by type using query builder', function () {
        SalaryAdjustment::factory()->create([
            'name' => 'Query Income Test',
            'type' => SalaryAdjustmentTypeEnum::INCOME
        ]);
        SalaryAdjustment::factory()->create([
            'name' => 'Query Deduction Test',
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION
        ]);

        $testIncomeAdjustments = SalaryAdjustment::query()
            ->where('type', SalaryAdjustmentTypeEnum::INCOME)
            ->where('name', 'Query Income Test')
            ->get();
        $testDeductionAdjustments = SalaryAdjustment::query()
            ->where('type', SalaryAdjustmentTypeEnum::DEDUCTION)
            ->where('name', 'Query Deduction Test')
            ->get();

        expect($testIncomeAdjustments)->toHaveCount(1);
        expect($testDeductionAdjustments)->toHaveCount(1);
        expect($testIncomeAdjustments->first()->type)->toBe(SalaryAdjustmentTypeEnum::INCOME);
        expect($testDeductionAdjustments->first()->type)->toBe(SalaryAdjustmentTypeEnum::DEDUCTION);
    });

    test('can filter by value type using query builder', function () {
        SalaryAdjustment::factory()->create([
            'name' => 'Query Absolute Test',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE
        ]);
        SalaryAdjustment::factory()->create([
            'name' => 'Query Percentage Test',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE
        ]);
        SalaryAdjustment::factory()->create([
            'name' => 'Query Formula Test',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA
        ]);

        $testAbsoluteAdjustments = SalaryAdjustment::query()
            ->where('value_type', SalaryAdjustmentValueTypeEnum::ABSOLUTE)
            ->where('name', 'Query Absolute Test')
            ->get();
        $testPercentageAdjustments = SalaryAdjustment::query()
            ->where('value_type', SalaryAdjustmentValueTypeEnum::PERCENTAGE)
            ->where('name', 'Query Percentage Test')
            ->get();
        $testFormulaAdjustments = SalaryAdjustment::query()
            ->where('value_type', SalaryAdjustmentValueTypeEnum::FORMULA)
            ->where('name', 'Query Formula Test')
            ->get();

        expect($testAbsoluteAdjustments)->toHaveCount(1);
        expect($testPercentageAdjustments)->toHaveCount(1);
        expect($testFormulaAdjustments)->toHaveCount(1);
    });

    test('can filter by custom value requirement', function () {
        SalaryAdjustment::factory()->create([
            'name' => 'Query Custom Value Test',
            'requires_custom_value' => true
        ]);
        SalaryAdjustment::factory()->create([
            'name' => 'Query Fixed Value Test',
            'requires_custom_value' => false
        ]);

        $testCustomValueAdjustments = SalaryAdjustment::query()
            ->where('requires_custom_value', true)
            ->where('name', 'Query Custom Value Test')
            ->get();
        $testFixedValueAdjustments = SalaryAdjustment::query()
            ->where('requires_custom_value', false)
            ->where('name', 'Query Fixed Value Test')
            ->get();

        expect($testCustomValueAdjustments)->toHaveCount(1);
        expect($testFixedValueAdjustments)->toHaveCount(1);
        expect($testCustomValueAdjustments->first()->requires_custom_value)->toBeTrue();
        expect($testFixedValueAdjustments->first()->requires_custom_value)->toBeFalse();
    });
});