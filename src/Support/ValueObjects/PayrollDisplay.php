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
use Barryvdh\DomPDF\Facade\Pdf as PDFFacade;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

readonly class PayrollDisplay
{
    public string $companyName;
    public string $dateString;
    public EloquentCollection $incomes;
    public EloquentCollection $deductions;
    public Collection $details;
    public TotalRowDisplay $totals;

    protected PDF $PDF;
    protected const DEFAULT_COLUMNS_NUMBER = 6;

    public function __construct(
        Payroll $payroll
    ) {
        if ($payroll->type->isMonthly()) {
            $payroll->period = $payroll->period->endOfMonth();
        }

        $this->companyName = $payroll->company->name;

        $this->dateString = $payroll->period->translatedFormat('d \d\e F \d\e\l Y');


        $parsedSalaryAdjustments = $payroll->salaryAdjustments
            ->groupBy('type')
            ->mapWithKeys(
                fn (Collection $adjustments, int $type) =>
                [
                    SalaryAdjustmentTypeEnum::from($type)->getKey() => $adjustments->keyBy('parser_alias'),
                ]
            );

        $this->incomes = $parsedSalaryAdjustments->get(SalaryAdjustmentTypeEnum::INCOME->getKey(), new EloquentCollection());
        $this->deductions = $parsedSalaryAdjustments->get(SalaryAdjustmentTypeEnum::DEDUCTION->getKey(), new EloquentCollection());

        $this->details = $payroll->details->mapInto(PayrollDetailDisplay::class)->toBase();

        $this->totals = new TotalRowDisplay($payroll->salaryAdjustments, $this->details);

        $this->PDF = PDFFacade::loadView('exports.payroll.index', [
            'company_name' => $this->companyName,
            'date_string' => $this->dateString,
            'incomes' => $this->incomes,
            'deductions' => $this->deductions,
            'details' => $this->details,
            'totals' => $this->totals,
            'is_pdf_export' => true,
        ])
            ->setPaper([0.0, 0.0, 612.00, (($this->deductions->count() + $this->incomes->count() + self::DEFAULT_COLUMNS_NUMBER) * 99)], 'landscape');
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

    protected function getPDF(): PDF
    {
        return $this->PDF;
    }

    public function renderPDF(): string
    {
        return $this->getPDF()->output();
    }

    public function streamPDF(): Response
    {
        return $this->getPDF()->stream();
    }
}
