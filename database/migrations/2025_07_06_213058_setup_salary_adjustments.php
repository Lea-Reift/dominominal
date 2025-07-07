<?php

declare(strict_types=1);

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    protected array $incomes = [
        [
            'name' => 'Comisiones',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'requires_custom_value' => true,
        ],
        [
            'name' => 'Horas extra',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'requires_custom_value' => true,
        ],
        [
            'name' => 'Vacaciones',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'requires_custom_value' => true,
        ],
    ];

    protected array $deductions = [
        [
            'name' => 'SFS',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => '((TOTAL_INGRESOS - HORAS_EXTRA) * 3.04)/100',
            'requires_custom_value' => false,
        ],
        [
            'name' => 'AFP',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => '((TOTAL_INGRESOS - HORAS_EXTRA) * 2.87)/100',
            'requires_custom_value' => false,
        ],
        [
            'name' => 'Dependientes adicionales',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'requires_custom_value' => true,
        ],
        [
            'name' => 'ISR',
            'value_type' => SalaryAdjustmentValueTypeEnum::FORMULA,
            'value' => 'RENGLONES_ISR[RENGLON_ISR]',
            'requires_custom_value' => false,
        ],
        [
            'name' => 'CxC',
            'value_type' => SalaryAdjustmentValueTypeEnum::ABSOLUTE,
            'requires_custom_value' => true,
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->incomes as $income) {
            $this->createAdjustment($income, SalaryAdjustmentTypeEnum::INCOME);
        }

        foreach ($this->deductions as $deduction) {
            $this->createAdjustment($deduction, SalaryAdjustmentTypeEnum::DEDUCTION);
        }
    }

    public function createAdjustment(array $adjustment, SalaryAdjustmentTypeEnum $type): SalaryAdjustment
    {
        return SalaryAdjustment::query()->create([
            'type' => $type,
            'name' => $adjustment['name'],
            'parser_alias' => str($adjustment['name'])->slug('_')->upper(),
            'value_type' => $adjustment['value_type'],
            'value' => $adjustment['value'] ?? null,
            'requires_custom_value' => $adjustment['requires_custom_value'],
        ]);
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => SalaryAdjustment::query()->truncate());
    }
};
