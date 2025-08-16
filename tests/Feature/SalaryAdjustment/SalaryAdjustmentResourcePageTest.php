<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Models\User;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\Resources\SalaryAdjustmentResource\Pages\ManageSalaryAdjustments;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('SalaryAdjustmentResource Page', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('can render salary adjustment list page', function () {
        Livewire::test(ManageSalaryAdjustments::class)
            ->assertSuccessful()
            ->assertSee('Ajustes Salariales')
            ->assertSee('Crear ajuste salarial');
    });

    test('displays salary adjustments in table', function () {
        $initialCount = SalaryAdjustment::count();

        SalaryAdjustment::factory()->create([
            'name' => 'Test Adjustment',
            'type' => SalaryAdjustmentTypeEnum::INCOME,
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'value' => '500.00'
        ]);

        expect(SalaryAdjustment::count())->toBe($initialCount + 1);
        expect(SalaryAdjustment::where('name', 'Test Adjustment')->exists())->toBeTrue();
    });

    test('can sort adjustments by type', function () {
        SalaryAdjustment::factory()->create([
            'name' => 'Income Test',
            'type' => SalaryAdjustmentTypeEnum::INCOME
        ]);
        SalaryAdjustment::factory()->create([
            'name' => 'Deduction Test',
            'type' => SalaryAdjustmentTypeEnum::DEDUCTION
        ]);

        $testAdjustments = SalaryAdjustment::whereIn('name', ['Income Test', 'Deduction Test'])
            ->orderBy('type')
            ->get();

        expect($testAdjustments->first()->type)->toBe(SalaryAdjustmentTypeEnum::INCOME);
        expect($testAdjustments->last()->type)->toBe(SalaryAdjustmentTypeEnum::DEDUCTION);
    });

    test('can sort adjustments by value type', function () {
        SalaryAdjustment::factory()->create([
            'name' => 'Absolute Test',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE
        ]);
        SalaryAdjustment::factory()->create([
            'name' => 'Percentage Test',
            'value_type' => SalaryAdjustmentValueTypeEnum::PERCENTAGE
        ]);

        $testAdjustments = SalaryAdjustment::whereIn('name', ['Absolute Test', 'Percentage Test'])
            ->orderBy('value_type')
            ->get();

        expect($testAdjustments->first()->value_type)->toBe(SalaryAdjustmentValueTypeEnum::ABSOLUTE);
        expect($testAdjustments->last()->value_type)->toBe(SalaryAdjustmentValueTypeEnum::PERCENTAGE);
    });
});
