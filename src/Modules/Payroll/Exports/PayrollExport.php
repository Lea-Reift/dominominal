<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Exports;

use App\Support\ValueObjects\PayrollDisplay;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollExport implements FromView, ShouldAutoSize, WithDefaultStyles, WithStyles
{
    use Exportable;

    protected int $baseRows = 4;

    protected int $baseColumns = 6;

    public function __construct(
        protected PayrollDisplay $payrollDisplay
    ) {
    }

    public function defaultStyles(Style $defaultStyle)
    {
        $defaultStyle->getFont()->setName('Times New Roman');
    }

    public function styles(Worksheet $sheet)
    {
        $lettersRange = range('A', 'Z');
        // Document Header
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(22);

        $sheet->getStyle('A2:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:A3')->getFont()->setSize(16);

        //Table Header
        $headerTotal = 6 + $this->payrollDisplay->incomes->count() + $this->payrollDisplay->deductions->count();
        $maxColumnIndex = $headerTotal - 1;
        $maxColumn = $lettersRange[$maxColumnIndex];
        $maxRow = $this->baseRows + $this->payrollDisplay->details->count();
        $employeeMinRow = $this->baseRows + 1;
        $formulaRow = $maxRow + 1;

        $headersRange = "A{$this->baseRows}:{$maxColumn}{$this->baseRows}";

        $sheet->getStyle($headersRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headersRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headersRange)->getFont()->setSize(11);
        $sheet->getStyle($headersRange)->getFont()->setBold(true);
        $sheet->getStyle($headersRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        $sheet->getStyle($headersRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
        $sheet->getStyle($headersRange)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
        $sheet->getRowDimension($this->baseRows)->setRowHeight(42);

        for ($i = 0; $i < $headerTotal; $i++) {
            $sheet->getStyle($lettersRange[$i] . $this->baseRows . ':' . $lettersRange[$i] . $maxRow + 1)->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);
        }

        //Body
        for ($i = 1; $i <= $this->payrollDisplay->details->count() + 1; $i++) {
            $row = $i + $this->baseRows;
            $sheet->getStyle("A{$row}:{$maxColumn}{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getStyle("C{$employeeMinRow}:{$maxColumn}{$formulaRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        //Formulas
        for ($i = 2; $i <= $maxColumnIndex; $i++) {
            $column = $lettersRange[$i];
            $formulaRange = $column . $employeeMinRow . ':' . $column . $maxRow;
            $sheet->setCellValue("{$column}{$formulaRow}", "=SUM({$formulaRange})");
        }

        //External Border
        $sheet->getStyle('A' . $this->baseRows . ':' . $maxColumn . $formulaRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
        $sheet->getStyle('A' . $this->baseRows . ':' . $maxColumn . $formulaRow)->getBorders()->getRight()->setBorderStyle(Border::BORDER_THICK);
        $sheet->getStyle('A' . $this->baseRows . ':' . $maxColumn . $formulaRow)->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK);
    }

    public function view(): View
    {
        return $this->payrollDisplay->render();
    }
}
