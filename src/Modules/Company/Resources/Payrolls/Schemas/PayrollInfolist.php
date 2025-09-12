<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Schemas;

use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Fluent;

class PayrollInfolist extends Fluent
{
    protected Payroll $payroll;

    public function __construct(
        protected Schema $schema,
    ) {
        $this->payroll = $schema->getModelInstance();
    }

    public static function configure(Schema $schema): Schema
    {
        $infolist = new self($schema)
            ->wrapper();

        return $infolist->schema
            ->components($infolist->attributes);
    }

    protected function wrapper(): self
    {
        $wrapper = Section::make('Empleados')
            ->components([
                $this->detailCard($this->payroll->details->first())
            ]);

        $this->set('wrapper', $wrapper);
        return $this;
    }

    protected function detailCard(PayrollDetail $detail): Section
    {
        return Section::make($detail->employee->full_name)
            ->columns([
                'md' => 2,
                'lg' => 4,
            ])
            ->disabled(false)
            ->components([
                TextEntry::make('information.raw_salary')
                    ->label('Salario Bruto')
                    ->state($detail->display->rawSalary),
                TextEntry::make('information.income_total')
                    ->label('Total de Ingresos')
                    ->state($detail->display->incomeTotal),
                TextEntry::make('information.deduction_total')
                    ->label('Total de Deducciones')
                    ->state($detail->display->deductionTotal),
                TextEntry::make('information.net_salary')
                    ->label('Salario Neto')
                    ->state($detail->display->netSalary),
                Section::make('Ajustes Salariales')
                    ->columnSpanFull()
                    ->contained(false)
                    ->columns(5)
                    ->disabled(false)
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('available_adjustments')
                            ->hiddenLabel()
                            ->bulkToggleable()
                            ->columns(2)
                            ->disabled(false)
                            ->columnSpan(2)
                            ->state($detail->salaryAdjustments->pluck('id'))
                            ->options($this->payroll->salaryAdjustments->pluck('name', 'id'))
                    ])
            ]);
    }
}
