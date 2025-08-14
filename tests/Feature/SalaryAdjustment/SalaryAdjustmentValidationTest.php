<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Models\User;
use App\Modules\Payroll\Models\SalaryAdjustment;

use function Pest\Laravel\{actingAs, assertDatabaseHas};

describe('SalaryAdjustment Validation', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('can create adjustment with long name', function () {
        $longName = str_repeat('a', 255);

        $adjustment = SalaryAdjustment::factory()->create([
            'name' => $longName,
            'parser_alias' => 'TEST_LONG_NAME',
        ]);

        expect($adjustment->name)->toBe($longName);
    });

    test('can create adjustment with long parser alias', function () {
        $longAlias = str_repeat('A', 255);

        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Test Long Alias',
            'parser_alias' => $longAlias,
        ]);

        expect($adjustment->parser_alias)->toBe($longAlias);
    });

    test('accepts valid enum values for type field', function () {
        foreach (SalaryAdjustmentTypeEnum::cases() as $type) {
            $adjustment = SalaryAdjustment::factory()->create([
                'type' => $type,
                'name' => "Test {$type->getLabel()}",
            ]);

            expect($adjustment->type)->toBe($type);

            assertDatabaseHas(SalaryAdjustment::class, [
                'type' => $type->value,
                'name' => "Test {$type->getLabel()}",
            ]);
        }
    });

    test('accepts valid enum values for value_type field', function () {
        foreach (SalaryAdjustmentValueTypeEnum::cases() as $valueType) {
            $data = [
                'type' => SalaryAdjustmentTypeEnum::INCOME,
                'value_type' => $valueType,
                'name' => "Test {$valueType->getLabel()}",
                'requires_custom_value' => false,
                'ignore_in_deductions' => false,
                'is_absolute_adjustment' => false,
            ];

            if ($valueType !== SalaryAdjustmentValueTypeEnum::FORMULA) {
                $data['value'] = $valueType === SalaryAdjustmentValueTypeEnum::PERCENTAGE ? '10' : '100.00';
            } else {
                $data['value'] = 'SALARIO_BASE * 0.1';
            }

            $adjustment = SalaryAdjustment::factory()->create($data);

            expect($adjustment->value_type)->toBe($valueType);

            assertDatabaseHas(SalaryAdjustment::class, [
                'value_type' => $valueType->value,
                'name' => "Test {$valueType->getLabel()}",
            ]);
        }
    });
});