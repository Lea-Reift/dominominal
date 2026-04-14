<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

class ManualVoucherDisplay
{
    public string $name;
    public string $documentNumber;
    public float $rawSalary;
    public array $incomesData;
    public array $deductionsData;
    public array $adjustmentNamesData;
    public float $incomeTotal;
    public float $deductionTotal;
    public float $netSalary;
    public string $period;
    public string $companyName;

    public function __construct(
        string $name,
        string $documentNumber,
        float $salary,
        string $period,
        array $adjustments,
        string $companyName
    ) {
        $this->name = $name;
        $this->documentNumber = $documentNumber;
        $this->rawSalary = $salary;
        $this->period = $period;
        $this->companyName = $companyName;

        $incomes = [];
        $deductions = [];
        $adjustmentNames = [];

        foreach ($adjustments as $adjustment) {
            $adjustmentNames[$adjustment['alias']] = $adjustment['name'];

            if ($adjustment['type'] === 'income') {
                $incomes[$adjustment['alias']] = (float) $adjustment['value'];
            } else {
                $deductions[$adjustment['alias']] = (float) $adjustment['value'];
            }
        }

        $this->incomesData = $incomes;
        $this->deductionsData = $deductions;
        $this->adjustmentNamesData = $adjustmentNames;

        $this->incomeTotal = $this->rawSalary + array_sum($incomes);
        $this->deductionTotal = array_sum($deductions);
        $this->netSalary = $this->incomeTotal - $this->deductionTotal;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'incomes' => collect($this->incomesData),
            'deductions' => collect($this->deductionsData),
            'adjustmentNames' => collect($this->adjustmentNamesData),
            default => null,
        };
    }

    public function render(): View
    {
        return view('components.manual-payment-voucher-table', ['detail' => $this]);
    }

    protected function getPDF(): PDF
    {
        return FacadePdf::loadView('components.manual-payment-voucher-table', ['detail' => $this]);
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
