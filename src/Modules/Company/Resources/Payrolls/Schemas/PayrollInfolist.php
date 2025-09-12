<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Schemas;

use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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
        $wrapper = Repeater::make('details')
            ->relationship()
            ->addable(false)
            ->columnSpanFull()
            ->columns([
                'default' => 2,
                'lg' => 4,
            ])
            ->schema([
                Fieldset::make('Información')
                    ->key('information')
                    ->columns([
                        'default' => 2,
                        'lg' => 4,
                    ])
                    ->partiallyRenderAfterStateUpdated()
                    ->components([
                        TextEntry::make('display.rawSalary')
                            ->label('Salario Bruto'),
                        TextEntry::make('display.incomeTotal')
                            ->label('Total de Ingresos'),
                        TextEntry::make('display.deductionTotal')
                            ->label('Total de Deducciones'),
                        TextEntry::make('display.netSalary')
                            ->label('Salario Neto'),
                    ]),
                Fieldset::make('Ajustes Salariales')
                    ->columnSpanFull()
                    ->columns(6)
                    ->schema([
                        CheckboxList::make('salaryAdjustments')
                            ->relationship(
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (EloquentBuilder $query) => $query
                                    ->whereIn('salary_adjustments.id', $this->payroll->salaryAdjustments->modelKeys())
                            )
                            ->hiddenLabel()
                            ->bulkToggleable()
                            ->columns(2)
                            ->columnSpan(2)
                            ->live()
                            ->afterStateUpdated(function (array $state, CheckboxList $component, PayrollDetail $record) {
                                $recordAdjustments = $this->payroll->salaryAdjustments
                                    ->whereIn('id', $state)
                                    ->mapWithKeys(fn (SalaryAdjustment $adjustment) => [
                                        $adjustment->id => [
                                            'custom_value' => $record->salaryAdjustments->find($adjustment->id)
                                                ?->detailSalaryAdjustmentValue
                                                ->custom_value
                                        ]
                                    ]);

                                $record->salaryAdjustments()->sync($recordAdjustments);
                            })
                    ])
            ]);

        $this->set('wrapper', $wrapper);
        return $this;
    }
}
