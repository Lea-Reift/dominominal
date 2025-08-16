<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Models\User;
use App\Modules\Payroll\Models\SalaryAdjustment;

use function Pest\Laravel\actingAs;

describe('SalaryAdjustment Factory', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('factory addition state creates income adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->addition()->create();

        expect($adjustment->type)->toBe(SalaryAdjustmentTypeEnum::INCOME);
    });

    test('factory deduction state creates deduction adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->deduction()->create();

        expect($adjustment->type)->toBe(SalaryAdjustmentTypeEnum::DEDUCTION);
    });

    test('factory fixed state creates absolute value type', function () {
        $adjustment = SalaryAdjustment::factory()->fixed()->create();

        expect($adjustment->value_type)->toBe(SalaryAdjustmentValueTypeEnum::ABSOLUTE);
    });

    test('factory percentage state creates percentage value type', function () {
        $adjustment = SalaryAdjustment::factory()->percentage()->create();

        expect($adjustment->value_type)->toBe(SalaryAdjustmentValueTypeEnum::PERCENTAGE);
    });

    test('factory creates unique parser aliases', function () {
        $adjustments = SalaryAdjustment::factory()->count(10)->create();

        $aliases = $adjustments->pluck('parser_alias')->toArray();
        $uniqueAliases = array_unique($aliases);

        expect($aliases)->toHaveCount(count($uniqueAliases));
    });
});
