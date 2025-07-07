<?php

declare(strict_types=1);

namespace App\Support\ValueObjects\PayrollDisplay;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Support\Collection;
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
        $this->rawSalary = (float) $detail->getParsedPayrollSalary();
        $adjustments = $detail->payroll->salaryAdjustments->keyBy('parser_alias');

        $incomes = collect();
        $deductions = collect();

        $adjustments = SalaryAdjustmentParser::make($detail)
            ->variables()
            // ->when(str_contains($detail->employee->name, 'JOSE'), fn ($collection) => $collection->dd())
            ->filter(fn (mixed $value) => is_float($value))
            ->each(fn (float $value, string $key) => match ($adjustments->get($key)?->type) {
                SalaryAdjustmentTypeEnum::INCOME => $incomes->put($key, $value),
                SalaryAdjustmentTypeEnum::DEDUCTION => $deductions->put($key, $value),
                default => null
            });

        $this->incomes = $incomes;
        $this->deductions = $deductions;

        $this->incomeTotal = $this->rawSalary +  $this->incomes->sum();
        $this->deductionTotal = $this->deductions->sum();
        $this->netSalary = $this->incomeTotal - $this->deductionTotal;
    }
}
