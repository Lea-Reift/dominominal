<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Schemas;

use App\Modules\Company\Resources\Payrolls\Pages\ViewPayroll;
use App\Modules\Payroll\Actions\TableActions\AddEmployeeAction;
use App\Modules\Payroll\Actions\TableActions\GenerateSecondaryPayrollsAction;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\PayrollDetailSalaryAdjustment;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Fluent;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class PayrollInfolist extends Fluent
{
    protected Payroll $payroll;
    protected Section $wrapperSection;
    protected Repeater $repeaterComponent;
    protected Fieldset $detailInformationFieldset;
    protected Fieldset $detailAdjustmentsFieldset;

    public function __construct(
        protected Schema $schema,
    ) {
        $this->payroll = $schema->getModelInstance();
    }

    public static function configure(Schema $schema): Schema
    {
        $infolist = new self($schema)
            ->wrapper()
            ->repeater()
            ->detailInformation()
            ->detailAdjustments();

        return $infolist->schema
            ->components($infolist->merge());
    }

    protected function merge(): Section
    {
        return $this->wrapperSection
            ->components([
                $this->repeaterComponent
                    ->schema([
                        $this->detailInformationFieldset,
                        $this->detailAdjustmentsFieldset,
                    ])
            ]);
    }

    protected function wrapper(): self
    {
        $this->wrapperSection = Section::make();

        return $this;
    }

    public function repeater(): self
    {
        $this->repeaterComponent = Repeater::make('details')
            ->label('Empleados')
            ->relationship()
            ->addable(false)
            ->columnSpanFull()
            ->itemLabel(fn (array $state) => $this->payroll->employees->findOrFail($state['employee_id'])->full_name)
            ->afterLabel([
                GenerateSecondaryPayrollsAction::make($this->payroll)->button(),
                AddEmployeeAction::make($this->payroll)->button(),
            ])
            ->deleteAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Remover empleado')
                    ->action(function (mixed $arguments, Repeater $component, ViewPayroll $livewire) {
                        $detailId = Str::after($arguments['item'], '-');
                        $items = $component->getRawState();
                        /** @var Payroll $payroll */
                        $payroll = $component->getModelInstance();
                        $payrollDetail = $payroll->details->findOrFail($detailId);
                        unset($items[$arguments['item']]);

                        $component->rawState($items);

                        $component->callAfterStateUpdated();
                        $component->partiallyRender();
                        $payrollDetail->delete();

                        $livewire->dispatch('updatePayrollData');

                        return Notification::make('edit_payroll_details')
                            ->title('Datos guardados')
                            ->success()
                            ->send();
                    })
            )
            ->columns([
                'default' => 2,
                'lg' => 4,
            ]);

        return $this;
    }

    protected function detailInformation(): self
    {
        $this->detailInformationFieldset = Fieldset::make('Información')
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
            ]);

        return $this;
    }

    protected function detailAdjustments(): self
    {
        $this->detailAdjustmentsFieldset = Fieldset::make('Ajustes Salariales')
            ->columnSpanFull()
            ->columns(5)
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
                    ->partiallyRenderAfterStateUpdated()
                    ->afterStateUpdated(function (array $state, CheckboxList $component, PayrollDetail $record, ViewPayroll $livewire) {
                        $recordAdjustments = $this->payroll->salaryAdjustments
                            ->whereIn('id', $state)
                            ->mapWithKeys(fn (SalaryAdjustment $adjustment) => [
                                $adjustment->id => [
                                    'custom_value' => $record->salaryAdjustments->find($adjustment->id)
                                        ?->detailSalaryAdjustmentValue
                                        ->custom_value
                                ]
                            ]);

                        $statePath = $component->getStatePath();

                        $record->salaryAdjustments()->sync($recordAdjustments);

                        $record->refresh();
                        $livewire->dispatch('updatePayrollData');
                        $livewire->refreshFormData([str($statePath)->after('.')->beforeLast('.')->toString()]);
                    }),

                Grid::make()
                    ->columns(3)
                    ->key('salary_adjustments_grid')
                    ->columnSpan(3)
                    ->partiallyRenderAfterStateUpdated()
                    ->schema(fn (PayrollDetail $record) => [
                        Grid::make()
                            ->columns(3)
                            ->schema(
                                $record->salaryAdjustments->where('requires_custom_value', false)
                                    ->map(
                                        fn (SalaryAdjustment $adjustment) =>
                                        TextEntry::make('display.netSalary')
                                            ->state($record->display->salaryAdjustments->get($adjustment->parser_alias, 0))
                                            ->label($adjustment->name)
                                            ->afterLabel($adjustment->type->getLabel())
                                    )
                                    ->toArray()
                            ),
                        Repeater::make('editableSalaryAdjustmentValues')
                            ->relationship(
                                modifyQueryUsing: fn (EloquentBuilder $query) => $query
                                    ->with(['salaryAdjustment', 'payrollDetail'])
                                    ->orderByLeftPowerJoins('salaryAdjustment.type')
                                    ->orderByLeftPowerJoins('salaryAdjustment.requires_custom_value', 'desc')
                            )
                            ->grid(3)
                            ->columnSpanFull()
                            ->hiddenLabel()
                            ->itemLabel(fn (array $state, PayrollDetail $record) => $record->salaryAdjustments->find($state['salary_adjustment_id'])->name)
                            ->deletable(false)
                            ->addable(false)
                            ->simple(
                                TextInput::make('custom_value')
                                    ->beforeLabel(fn (?PayrollDetailSalaryAdjustment $record) => str($record?->salaryAdjustment->name)->wrap('<b>', '</b>')->toHtmlString())
                                    ->belowContent(fn (?PayrollDetailSalaryAdjustment $record) => $record?->salaryAdjustment->type->getLabel())
                                    ->mask(RawJs::make('$money($input)'))
                                    ->step(0.01)
                                    ->extraAlpineAttributes([
                                        'x-on:keydown.enter.prevent' => '$el.blur()',
                                    ])
                                    ->disabled(fn (?PayrollDetailSalaryAdjustment $record) => !$record?->salaryAdjustment->requires_custom_value)
                                    ->formatStateUsing(function (?float $state, ?PayrollDetailSalaryAdjustment $record) {
                                        $value = $record?->salaryAdjustment->requires_custom_value ?? true
                                            ? $state
                                            : $record->payrollDetail->display->salaryAdjustments->get($record->salaryAdjustment->parser_alias, 0);

                                        return Number::format(parse_float((string)$value), 2);
                                    })
                                    ->placeholder('Ingrese el valor')
                            )
                    ])
            ]);

        return $this;
    }
}
