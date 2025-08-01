<?php

declare(strict_types=1);

namespace App\Support\ValueObjects\PayrollDisplay;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Support\Collection;
use App\Support\SalaryAdjustmentParser;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Illuminate\Contracts\View\View;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

readonly class PayrollDetailDisplay
{
    public string $name;
    public Company $company;
    public Payroll $payroll;
    public string $documentNumber;
    public float $rawSalary;
    public Collection $incomes;
    public Collection $deductions;
    public float $incomeTotal;
    public float $deductionTotal;
    public float $netSalary;
    public Collection $adjustmentNames;

    protected PDF $PDF;

    public function __construct(
        PayrollDetail $detail
    ) {
        $detail->loadMissing([
            'employee',
            'salary',
            'salaryAdjustments',
            'payroll' => [
                'salaryAdjustments',
                'company',
            ]
        ]);

        $this->name = $detail->employee->full_name;
        $this->company = $detail->payroll->company;
        $this->payroll = $detail->payroll;
        $this->documentNumber = $detail->employee->document_number;

        $this->rawSalary = (float) $detail->getParsedPayrollSalary();
        $adjustments = $detail->payroll->salaryAdjustments->keyBy('parser_alias');

        $this->adjustmentNames = $adjustments->pluck('name', 'parser_alias');

        $incomes = collect();
        $deductions = collect();

        $adjustments = SalaryAdjustmentParser::make($detail)
            ->variables()
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


        $this->PDF = FacadePdf::loadView('components.payment-voucher-table', ['detail' => $this]);
    }

    public function render(): View
    {
        return view('components.payment-voucher-table', ['detail' => $this]);
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
