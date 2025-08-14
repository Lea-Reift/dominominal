<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Models\User;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\Resources\SalaryAdjustmentResource\Pages\ManageSalaryAdjustments;
use Livewire\Livewire;

use function Pest\Laravel\{actingAs, assertDatabaseHas};

describe('SalaryAdjustment Edge Cases', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('handles empty table gracefully', function () {
        SalaryAdjustment::query()->delete();

        Livewire::test(ManageSalaryAdjustments::class)
            ->assertSuccessful();

        expect(SalaryAdjustment::count())->toBe(0);
    });

    test('handles large number of records', function () {
        $initialCount = SalaryAdjustment::count();
        SalaryAdjustment::factory()->count(50)->create();

        expect(SalaryAdjustment::count())->toBe($initialCount + 50);

        Livewire::test(ManageSalaryAdjustments::class)
            ->assertSuccessful();
    });

    test('can create adjustment with unicode characters in name', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Bōnus Especiál çon acentós',
        ]);

        expect($adjustment->name)->toBe('Bōnus Especiál çon acentós');

        assertDatabaseHas(SalaryAdjustment::class, [
            'name' => 'Bōnus Especiál çon acentós',
        ]);
    });

    test('can handle decimal values with precision', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Precise Adjustment',
            'value' => '1234.5678',
        ]);

        expect($adjustment->value)->toBe('1234.5678');

        assertDatabaseHas(SalaryAdjustment::class, [
            'name' => 'Precise Adjustment',
            'value' => '1234.5678',
        ]);
    });

    test('can create adjustment with zero value', function () {
        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Zero Value Adjustment',
            'value' => '0',
        ]);

        expect($adjustment->value)->toBe('0');

        assertDatabaseHas(SalaryAdjustment::class, [
            'name' => 'Zero Value Adjustment',
            'value' => '0',
        ]);
    });

    test('can handle complex formula expressions', function () {
        $complexFormula = '(SALARIO_BASE * 0.15) + (HORAS_EXTRA * 50) - DESCUENTOS';

        $adjustment = SalaryAdjustment::factory()->create([
            'name' => 'Complex Formula',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => $complexFormula,
        ]);

        expect($adjustment->value)->toBe($complexFormula);

        assertDatabaseHas(SalaryAdjustment::class, [
            'name' => 'Complex Formula',
            'value' => $complexFormula,
        ]);
    });
});