<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\Payrolls\Widgets;

use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Illuminate\Support\Collection;

/**
 * @property array{rawSalary: float, incomesTotal: float, incomes: Collection, deductions: Collection, deductionsTotal: float, netSalary: float} $totalRowDisplay
 */
class PayrollDetailAmountWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;
    protected static bool $isLazy = false;

    protected string $view = 'components.widgets.overview';

    public array $totalRowDisplay;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // $adjustmentvalues = $this->totalRowDisplay['incomes']->union($this->totalRowDisplay['deductions']);
        // $adjustments = SalaryAdjustment::query()
        //     ->whereIn('parser_alias', $adjustmentvalues->keys())
        //     ->pluck('name', 'parser_alias')
        //     ->sortBy(fn (string $name, string $adjustment) => $adjustmentvalues->keys()->search($adjustment))
        //     ->map(
        //         fn (string $name, string $adjustment) => Stat::make("Total {$name}", Number::currency($adjustmentvalues->get($adjustment)))
        //     );

        return [
            Stat::make('Total Salario Bruto', 5),
            // Stat::make('Total Salario Bruto', Number::currency($this->totalRowDisplay['rawSalary'])),
            // Stat::make('Total Ingresos', Number::currency($this->totalRowDisplay['incomesTotal'])),
            // Stat::make('Total Descuentos', Number::currency($this->totalRowDisplay['deductionsTotal'])),
            // Stat::make('Total Salario Neto', Number::currency($this->totalRowDisplay['netSalary'])),
            // ...$adjustments->toArray(),
        ];
    }


}
