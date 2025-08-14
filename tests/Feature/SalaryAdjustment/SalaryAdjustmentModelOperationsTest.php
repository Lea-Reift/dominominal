<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Models\User;
use App\Modules\Payroll\Models\SalaryAdjustment;

use function Pest\Laravel\{actingAs, assertDatabaseHas};

describe('SalaryAdjustment Model Operations', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('can create salary adjustment with required fields', function () {
        $adjustment = SalaryAdjustment::create([
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'name' => 'Bono de Desempeño',
            'parser_alias' => 'BONO_DESEMPENO',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1000.00',
            'requires_custom_value' => false,
            'ignore_in_deductions' => false,
            'is_absolute_adjustment' => false,
        ]);

        expect($adjustment)->not->toBeNull();
        expect($adjustment->name)->toBe('Bono de Desempeño');
        expect($adjustment->type)->toBe(SalaryAdjustmentTypeEnum::INCOME);
        expect($adjustment->value_type)->toBe(SalaryAdjustmentValueTypeEnum::ABSOLUTE);
        expect($adjustment->value)->toBe('1000.00');

        assertDatabaseHas(SalaryAdjustment::class, [
            'type' => SalaryAdjustmentTypeEnum::INCOME->value,
            'name' => 'Bono de Desempeño',
            'parser_alias' => 'BONO_DESEMPENO',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE->value,
            'value' => '1000.00',
            'requires_custom_value' => false,
            'ignore_in_deductions' => false,
            'is_absolute_adjustment' => false,
        ]);
    });

    test('can create different types of adjustments', function () {
        $incomeAdjustment = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'name' => 'Bonus Income',
        ]);
        
        $deductionAdjustment = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION,
            'name' => 'Tax Deduction',
        ]);

        expect($incomeAdjustment->type)->toBe(SalaryAdjustmentTypeEnum::INCOME);
        expect($deductionAdjustment->type)->toBe(SalaryAdjustmentTypeEnum::DEDUCTION);
    });

    test('can create custom value adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Horas Extra Custom',
            'parser_alias' => 'HORAS_EXTRA_CUSTOM',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'requires_custom_value' => true,
            'value' => null,
        ]);

        expect($adjustment->name)->toBe('Horas Extra Custom');
        expect($adjustment->requires_custom_value)->toBeTrue();
        expect($adjustment->value)->toBeNull();
    });

    test('can create formula-based adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Comisión',
            'parser_alias' => 'COMISION',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'SALARIO_BASE * 0.05',
        ]);

        expect($adjustment->name)->toBe('Comisión');
        expect($adjustment->value_type)->toBe(SalaryAdjustmentValueTypeEnum::FORMULA);
        expect($adjustment->value)->toBe('SALARIO_BASE * 0.05');
    });

    test('can create percentage-based adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION,
            'name' => 'Descuento Especial',
            'parser_alias' => 'DESCUENTO_ESPECIAL',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'value' => '10',
        ]);

        expect($adjustment->name)->toBe('Descuento Especial');
        expect($adjustment->value_type)->toBe(SalaryAdjustmentValueTypeEnum::PERCENTAGE);
        expect($adjustment->value)->toBe('10');
    });

    test('can edit salary adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Bonus Original',
            'value' => '500.00'
        ]);

        $adjustment->update([
            'name' => 'Bonus Actualizado',
            'value' => '750.00',
        ]);

        expect($adjustment->name)->toBe('Bonus Actualizado');
        expect($adjustment->value)->toBe('750.00');

        assertDatabaseHas(SalaryAdjustment::class, [
            'id' => $adjustment->id,
            'name' => 'Bonus Actualizado',
            'value' => '750.00',
        ]);
    });

    test('can change adjustment type', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME,
        ]);

        $adjustment->update(['type' => SalaryAdjustmentTypeEnum::DEDUCTION]);

        expect($adjustment->fresh()->type)->toBe(SalaryAdjustmentTypeEnum::DEDUCTION);
    });

    test('can change value type and adjust value accordingly', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '1000.00'
        ]);

        $adjustment->update([
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE,
            'value' => '15',
        ]);

        expect($adjustment->fresh()->value_type)->toBe(SalaryAdjustmentValueTypeEnum::PERCENTAGE);
        expect($adjustment->fresh()->value)->toBe('15');
    });

    test('can toggle custom value requirement', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'requires_custom_value' => false,
            'value' => '500.00'
        ]);

        $adjustment->update(['requires_custom_value' => true]);

        expect($adjustment->fresh()->requires_custom_value)->toBeTrue();
    });

    test('can toggle ignore in deductions for income adjustments', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'ignore_in_deductions' => false,
        ]);

        $adjustment->update(['ignore_in_deductions' => true]);

        expect($adjustment->fresh()->ignore_in_deductions)->toBeTrue();
    });

    test('can toggle absolute adjustment flag', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'is_absolute_adjustment' => false,
        ]);

        $adjustment->update(['is_absolute_adjustment' => true]);

        expect($adjustment->fresh()->is_absolute_adjustment)->toBeTrue();
    });
});