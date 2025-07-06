<?php

declare(strict_types=1);

namespace App\Support\ValueObjects\PayrollDisplay;

use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Support\Collection;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Support\SalaryAdjustmentParser;

readonly class DetailDisplay
{
    public string $name;
    public string $document_number;
    public float $rawSalary;
    public Collection $incomes;
    public Collection $deductions;
    public float $incomeTotal;
    public float $deductionTotal;
    public float $netSalary;

    public function __construct(
        PayrollDetail $detail
    ) {
        $detail->loadMissing(['employee', 'salary', 'salaryAdjustments', 'payroll' => ['salaryAdjustments']]);

        $this->name = $detail->employee->full_name;
        $this->document_number = $detail->employee->document_number;
        $this->rawSalary = (float) $detail->salary->amount;
        $adjustments = $detail->payroll->salaryAdjustments->keyBy('parser_alias');

        $adjustments = SalaryAdjustmentParser::make($detail)
            ->variablesAsCollection()
            ->groupBy(preserveKeys: true, groupBy: fn (float $_, string $key) => $adjustments->get($key)?->type?->getKey() ?? 'custom');

        $this->incomes = $adjustments[SalaryAdjustmentTypeEnum::INCOME->getKey()];
        $this->deductions = $adjustments[SalaryAdjustmentTypeEnum::DEDUCTION->getKey()];

        $this->incomeTotal = $this->rawSalary +  $this->incomes->sum();
        $this->deductionTotal = $this->deductions->sum();
        $this->netSalary = $this->incomeTotal - $this->deductionTotal;
    }
}
