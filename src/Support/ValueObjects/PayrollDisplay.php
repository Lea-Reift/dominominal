<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Modules\Payroll\Models\Payroll;
use App\Support\ValueObjects\PayrollDisplay\TotalRowDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Support\ValueObjects\PayrollDisplay\PayrollDetailDisplay;

readonly class PayrollDisplay
{
    public string $companyName;
    public string $dateString;
    public EloquentCollection $incomes;
    public EloquentCollection $deductions;
    public Collection $details;
    public TotalRowDisplay $totals;

    public function __construct(
        Payroll $payroll
    ) {
        $payroll->load([
            'company',
            'details' => [
                'payroll',
                'employee',
                'salary',
                'salaryAdjustments',
            ],
            'salaryAdjustments',
        ]);

        if ($payroll->type->isMonthly()) {
            $payroll->period = $payroll->period->endOfMonth();
        }

        $this->companyName = $payroll->company->name;

        $this->dateString = $payroll->period->translatedFormat('d \d\e F \d\e\l Y');

        [
            SalaryAdjustmentTypeEnum::INCOME->getKey() => $this->incomes,
            SalaryAdjustmentTypeEnum::DEDUCTION->getKey() => $this->deductions
        ] = $payroll->salaryAdjustments
            ->groupBy('type')
            ->mapWithKeys(
                fn (Collection $adjustments, int $type) =>
                [
                    SalaryAdjustmentTypeEnum::from($type)->getKey() => $adjustments->keyBy('parser_alias'),
                ]
            );

        $this->details = $payroll->details->mapInto(PayrollDetailDisplay::class)->toBase();

        $this->totals = new TotalRowDisplay($payroll->salaryAdjustments, $this->details);
    }

    public function render(): View
    {
        return view('exports.payroll.index', [
            'company_name' => $this->companyName,
            'date_string' => $this->dateString,
            'incomes' => $this->incomes,
            'deductions' => $this->deductions,
            'details' => $this->details,
            'totals' => $this->totals,
        ]);
    }
}
