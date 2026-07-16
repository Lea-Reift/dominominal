<?php

declare(strict_types=1);

namespace App\Support\ValueObjects\PayrollDisplay;

use App\Enums\SalaryAdjustmentTypeEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use App\Modules\Payroll\Models\SalaryAdjustment;

readonly class TotalRowDisplay
{
    public float $rawSalary;
    public float $incomesTotal;
    public Collection $incomes;
    public Collection $deductions;
    public Collection $salaryAdjustments;
    public float $deductionsTotal;
    public float $netSalary;

    /**
     * @param EloquentCollection<int, SalaryAdjustment> $payrollSalaryAdjustments
     * @param Collection<int, PayrollDetailDisplay> $details
     */
    public function __construct(
        EloquentCollection $payrollSalaryAdjustments,
        Collection $details
    ) {
        $this->rawSalary = $details->sum('rawSalary');
        $this->incomesTotal = $details->sum('incomeTotal');
        $this->deductionsTotal = $details->sum('deductionTotal');
        $this->netSalary = $details->sum('netSalary');
        $adjustments = $payrollSalaryAdjustments
            ->groupBy('type')
            ->map
            ->mapWithKeys(function (SalaryAdjustment $adjustment) use ($details) {
                return [$adjustment->parser_alias => $details->sum(function (PayrollDetailDisplay $detail) use ($adjustment) {
                    return $detail->{$adjustment->type->getKey(true)}->get($adjustment->parser_alias);
                })];
            });

        $this->incomes = $adjustments->get(SalaryAdjustmentTypeEnum::INCOME->value, new Collection());
        $this->deductions = $adjustments->get(SalaryAdjustmentTypeEnum::DEDUCTION->value, new Collection());
        $this->salaryAdjustments = $this->incomes->merge($this->deductions);
    }
}
