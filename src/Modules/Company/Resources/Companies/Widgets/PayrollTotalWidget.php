<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Companies\Widgets;

use App\Modules\Payroll\Models\Payroll;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use App\Enums\SalaryAdjustmentTypeEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

class PayrollTotalWidget extends StatsOverviewWidget
{
    public ?Payroll $record = null;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = [

        'sm' => 2,
        'lg' => 3,
        'xl' => 4
    ];

    protected function getAdjustmentsStats(): Section
    {
        return Section::make()
            ->contained(false)
            ->collapsed()
            ->columns(2)
            ->compact()
            ->afterHeader(fn (Section $component) => $component->isCollapsed() ? 'Mostrar ajustes salariales' : 'Ocultar ajustes salariales')
            ->schema(
                SalaryAdjustmentTypeEnum::collect()
                    ->map(
                        fn (SalaryAdjustmentTypeEnum $type) => Section::make(str($type->getLabel())->plural()->toString())
                            ->columns($this->columns)
                            ->columnSpan(1)
                            ->schema(
                                $this->record->display->totals->{$type->getKey(plural: true)}
                                    ->map(
                                        fn (float $total, string $parserAlias) => TextEntry::make($parserAlias)
                                            ->label($this->record->salaryAdjustments->firstWhere('parser_alias', $parserAlias)->name)
                                            ->state($total)
                                    )
                                    ->toArray()
                            )
                    )
                    ->toArray()
            );
    }

    protected $listeners = ['updatePayrollTotal' => '$refresh'];

    protected function getStats(): array
    {
        $totalRowDisplay = $this->record->display->totals;

        return [
            Stat::make('Total Salario Bruto', Number::dominicanCurrency($totalRowDisplay->rawSalary)),
            Stat::make('Total Ingresos', Number::dominicanCurrency($totalRowDisplay->incomesTotal)),
            Stat::make('Total Deducciones', Number::dominicanCurrency($totalRowDisplay->deductionsTotal)),
            Stat::make('Total Salario Neto', Number::dominicanCurrency($totalRowDisplay->rawSalary)),
            $this->getAdjustmentsStats()
        ];
    }

    public function getSectionContentComponent(): Component
    {
        return Section::make()
            ->heading('Detalles')
            ->description($this->getDescription())
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->gridContainer();
    }
}
