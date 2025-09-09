<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Fieldset;
use App\Modules\Company\Models\Employee;
use App\Modules\Payroll\Models\Payroll;
use Filament\Forms\Components\Select;
use App\Enums\SalaryTypeEnum;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\CheckboxList;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\PayrollDetailSalaryAdjustment;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Operation;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Modules\Company\Models\Salary;
use App\Modules\Company\Resources\Payrolls\Pages\ViewPayroll;
use App\Modules\Payroll\Actions\TableActions\AddEmployeeAction;
use App\Modules\Payroll\Actions\TableActions\GenerateSecondaryPayrollsAction;
use App\Modules\Payroll\Actions\TableRowActions\ShowPaymentVoucherAction;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

class PayrollForm
{
    protected Payroll $payroll;

    public function __construct(
        protected Schema $form
    ) {
        $this->configureForm();
    }

    public function schema(): Schema
    {
        return $this->form;
    }

    protected function payrollSection(): Section
    {
        return Section::make()
            ->hiddenOn(Operation::View)
            ->columns(2)
            ->schema(fn (Get $get) => [
                Select::make('type')
                    ->label('Tipo')
                    ->required()
                    ->live()
                    ->default(SalaryTypeEnum::MONTHLY)
                    ->options(SalaryTypeEnum::class)
                    ->native(false),

                Flatpickr::make('period')
                    ->label('Periodo')
                    ->format('Y-m-d')
                    ->default(now())
                    ->displayFormat($get('type') === SalaryTypeEnum::MONTHLY->value ? 'F-Y' : 'd-m-Y')
                    ->when(
                        $get('type') === SalaryTypeEnum::MONTHLY->value,
                        fn (Flatpickr $picker) => $picker->monthPicker()
                    )
                    ->unique(
                        modifyRuleUsing: fn (Unique $rule, ?Payroll $record) => $rule
                            ->when(
                                $record !== null,
                                fn (Unique $rule) => $rule
                                    ->where('company_id', $record->company_id)
                                    ->where('type', SalaryTypeEnum::MONTHLY)
                            )
                    )
                    ->closeOnDateSelection()
                    ->required(),

                Fieldset::make('Ajustes Salariales')
                    ->schema([
                        CheckboxList::make('incomes')
                            ->label('Ingresos')
                            ->relationship('incomes', 'name')
                            ->bulkToggleable()
                            ->descriptions(
                                fn () => SalaryAdjustment::query()
                                    ->incomes()
                                    ->get()
                                    // @phpstan-ignore-next-line
                                    ->mapWithKeys(function (SalaryAdjustment $adjustment) {
                                        if ($adjustment->requires_custom_value) {
                                            return [$adjustment->id => "{$adjustment->value_type->getLabel()}: Modificable"];
                                        }

                                        $description = "{$adjustment->value_type->getLabel()}: " . match ($adjustment->value_type) {
                                            SalaryAdjustmentValueTypeEnum::ABSOLUTE => Number::currency((float)$adjustment->value, 'DOP'),
                                            SalaryAdjustmentValueTypeEnum::PERCENTAGE => "{$adjustment->value}%",
                                            default => $adjustment->value
                                        };

                                        return [$adjustment->id => $description];
                                    })
                            )
                            ->getOptionLabelFromRecordUsing(fn (SalaryAdjustment $record) => Str::headline($record->name)),

                        CheckboxList::make('deductions')
                            ->label('Descuentos')
                            ->relationship('deductions', 'name')
                            ->bulkToggleable()
                            ->descriptions(
                                fn () => SalaryAdjustment::query()
                                    ->deductions()
                                    ->get()
                                    // @phpstan-ignore-next-line
                                    ->mapWithKeys(function (SalaryAdjustment $adjustment) {
                                        if ($adjustment->requires_custom_value) {
                                            return [$adjustment->id => "{$adjustment->value_type->getLabel()}: Modificable"];
                                        }

                                        $description = "{$adjustment->value_type->getLabel()}: " . match ($adjustment->value_type) {
                                            SalaryAdjustmentValueTypeEnum::ABSOLUTE => Number::currency((float)$adjustment->value, 'DOP'),
                                            SalaryAdjustmentValueTypeEnum::PERCENTAGE => "{$adjustment->value}%",
                                            default => $adjustment->value
                                        };

                                        return [$adjustment->id => $description];
                                    })
                            )
                            ->getOptionLabelFromRecordUsing(fn (SalaryAdjustment $record) => Str::headline($record->name)),
                    ])
            ]);
    }

    protected function payrollDetailsSectionSchema(): array
    {
        if (!isset($this->payroll)) {
            return [];
        }

        return [
            Repeater::make('details')
                ->label('Empleados')
                ->afterLabel([
                    GenerateSecondaryPayrollsAction::make($this->payroll)->button(),
                    AddEmployeeAction::make($this->payroll)->button(),
                ])
                ->disabledOn([Operation::Create->value, Operation::Edit->value])
                ->relationship(modifyQueryUsing: fn (EloquentBuilder $query) => $query->with('editableSalaryAdjustmentValues'))
                ->addable(false)
                ->reorderable()
                ->reorderableWithDragAndDrop()
                ->columns(12)
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
                ->extraItemActions([
                    ShowPaymentVoucherAction::make($this->payroll)
                ])
                ->itemLabel(function (array $state, Payroll $record) {
                    /** @var Employee $employee */
                    $employee = $record->employees->findOrFail($state['employee_id']);
                    return $employee->full_name;
                })
                ->schema([
                    Section::make('Información')
                        ->afterHeader(function (array $state) {
                            /** @var PayrollDetail $detail */
                            $detail = $this->payroll->details->findOrFail($state['id']);

                            return ActionGroup::make([])
                                ->label('Usar otro salario')
                                ->icon(Heroicon::CurrencyDollar)
                                ->badge()
                                ->actions([
                                    ...$detail->employee->salaries
                                        ->map(
                                            fn (Salary $salary) => Action::make("set_salary_{$salary->id}")
                                                ->label(Number::dominicanCurrency($salary->amount))
                                                ->record($salary)
                                                ->action(function (ViewPayroll $livewire) use ($salary, $detail) {
                                                    $detail->update([
                                                        'salary_id' => $salary->id,
                                                    ]);

                                                    $livewire->dispatch('updatePayrollData');
                                                    return Notification::make('edit_available_adjustments')
                                                        ->title('Datos guardados')
                                                        ->success()
                                                        ->send();
                                                })
                                        )
                                ]);
                        })
                        ->columnSpan(5)
                        ->columns(3)
                        ->schema([
                            Section::make()
                                ->columnSpan(1)
                                ->contained(false)
                                ->schema([
                                    TextEntry::make('parsedPayrollSalary')
                                        ->state(fn ($record) => $record->getParsedPayrollSalary())
                                        ->label('Salario'),
                                    TextEntry::make('display.incomeTotal')
                                        ->label('Ingresos'),
                                    TextEntry::make('display.deductionTotal')
                                        ->label('Deducciones'),
                                    TextEntry::make('display.netSalary')
                                        ->label('Total a pagar'),
                                ]),
                            CheckboxList::make('available_salary_adjustments')
                                ->label('Ajustes Salariales Disponibles')
                                ->columns(2)
                                ->columnSpan(2)
                                ->relationship(
                                    'salaryAdjustments',
                                    'name',
                                    fn (EloquentBuilder $query) => $query->whereIn('salary_adjustments.id', $this->payroll->salaryAdjustments->modelKeys())
                                )
                                ->bulkToggleable()
                                ->live()
                                ->afterStateUpdated(function (array $state, CheckboxList $component, Action $action, ViewPayroll $livewire) {
                                    $statePath = $component->getStatePath();

                                    $pathParts = explode('.', $statePath);
                                    $detailIndex = array_find($pathParts, fn (string $part) => str_contains($part, 'record-'));

                                    $payrollDetailId = $livewire->data['details'][$detailIndex]['id'];
                                    /** @var PayrollDetail $payrollDetail */
                                    $payrollDetail = PayrollDetail::query()->findOrFail($payrollDetailId);

                                    $payrollDetail->salaryAdjustments()->sync($state);

                                    $livewire->dispatch('updatePayrollData');
                                    $livewire->refreshFormData([str($statePath)->after('.')->beforeLast('.')->toString()]);

                                    return Notification::make('edit_available_adjustments')
                                        ->title('Datos guardados')
                                        ->success()
                                        ->send();
                                }),

                        ]),
                    Section::make('Ajustes Salariales')
                        ->columnSpan(7)
                        ->schema(fn (PayrollDetail $record) => [
                            Section::make()
                                ->contained(false)
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
                                        ->afterStateUpdated(function (?string $state, PayrollDetailSalaryAdjustment $record, ViewPayroll $livewire) {
                                            if (!is_null($state)) {
                                                $state = parse_float($state);
                                                $validationFails = match ($record->salaryAdjustment->value_type) {
                                                    SalaryAdjustmentValueTypeEnum::ABSOLUTE => match ($record->salaryAdjustment->type) {
                                                        SalaryAdjustmentTypeEnum::INCOME => $state < 0,
                                                        SalaryAdjustmentTypeEnum::DEDUCTION => $state > $record->payrollDetail->getParsedPayrollSalary() || $state < 0,
                                                    },
                                                    SalaryAdjustmentValueTypeEnum::PERCENTAGE => $state < 0 || $state > 100,
                                                    SalaryAdjustmentValueTypeEnum::FORMULA => empty($state)
                                                };

                                                if ($validationFails) {
                                                    Notification::make('adjustment_modification_failed')
                                                        ->title('Valor invalido')
                                                        ->body('El valor introducido no es correcto. Intente nuevamente')
                                                        ->danger()
                                                        ->color('danger')
                                                        ->seconds(5)
                                                        ->send();
                                                    return;
                                                }
                                            }

                                            DB::transaction(function () use ($record, $state, $livewire) {
                                                $record->update(['custom_value' => $state]);

                                                if ($record->payrollDetail->payroll->biweeklyPayrolls()->exists()) {
                                                    PayrollDetailSalaryAdjustment::query()
                                                        ->where('salary_adjustment_id', $record->salary_adjustment_id)
                                                        ->whereHas('payrollDetail.payroll.monthlyPayroll', fn (EloquentBuilder $query) => $query->where('id', $record->payrollDetail->payroll_id))
                                                        ->update([
                                                            'custom_value' => $state / 2
                                                        ]);
                                                    return;
                                                }

                                                $monthlyPayrollId = $record->payrollDetail->payroll->monthly_payroll_id;
                                                if (!$monthlyPayrollId) {
                                                    return;
                                                };

                                                $biweeklyPayrollsTotalValue = PayrollDetailSalaryAdjustment::query()
                                                    ->where('salary_adjustment_id', $record->salary_adjustment_id)
                                                    ->whereHas('payrollDetail.payroll.monthlyPayroll', fn (EloquentBuilder $query) => $query->where('id', $monthlyPayrollId))
                                                    ->numericAggregate('sum', ['custom_value']);

                                                PayrollDetailSalaryAdjustment::query()
                                                    ->where('salary_adjustment_id', $record->salary_adjustment_id)
                                                    ->whereHas(
                                                        'payrollDetail',
                                                        fn (EloquentBuilder $query) => $query
                                                            ->where('employee_id', $record->payrollDetail->employee_id)
                                                            ->where('payroll_id', $monthlyPayrollId)
                                                    )
                                                    ->update([
                                                        'custom_value' => $biweeklyPayrollsTotalValue,
                                                    ]);
                                                $livewire->refreshFormData(['details', 'details_display_section']);
                                            });

                                            $livewire->dispatch('updatePayrollData');

                                            Notification::make('adjustment_modification_success')
                                                ->title('Datos guardados')
                                                ->success()
                                                ->color('success')
                                                ->seconds(5)
                                                ->send();
                                        })
                                        ->live(true),
                                ),
                        ])
                ])
        ];
    }

    protected function configureForm(): void
    {
        $this->form
            ->components(function (?Payroll $record) {
                $this->setPayroll($record);
                return [
                    $this->payrollSection(),
                    Section::make()
                        ->visibleOn(Operation::View)
                        ->disabledOn([Operation::Create->value, Operation::Edit->value])
                        ->schema($this->payrollDetailsSectionSchema())
                ];
            });
    }

    protected function setPayroll(?Payroll $payroll): void
    {
        if ($payroll !== null) {
            $this->payroll = $payroll;
        }
    }

    public static function configure(Schema $schema): Schema
    {
        return new self($schema)->schema();
    }
}
