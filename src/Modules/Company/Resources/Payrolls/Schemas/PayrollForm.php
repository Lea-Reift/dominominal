<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Fieldset;
use App\Modules\Payroll\Models\Payroll;
use Filament\Forms\Components\Select;
use App\Enums\SalaryTypeEnum;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\CheckboxList;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\PayrollDetail;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Operation;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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
            ->schema([
                Select::make('type')
                    ->label('Tipo')
                    ->required()
                    ->live()
                    ->default(SalaryTypeEnum::MONTHLY->value)
                    ->options(SalaryTypeEnum::class)
                    ->native(false),

                Flatpickr::make('period')
                    ->label('Periodo')
                    ->id('month-select')
                    ->format('Y-m-d')
                    ->monthPicker()
                    ->unique(
                        modifyRuleUsing: fn (Unique $rule, ?Payroll $record) => $rule
                            ->unless(
                                is_null($record),
                                fn (Unique $rule) => $rule
                                    ->where('company_id', $record->company_id)
                                    ->where('type', SalaryTypeEnum::MONTHLY)
                            )
                    )
                    ->default(now())
                    ->visible(fn (Get $get) => $get('type')?->isMonthly())
                    ->disabled(fn (Get $get) => $get('type')?->isNotMonthly())
                    ->displayFormat('F-Y')
                    ->closeOnDateSelection()
                    ->required(),

                Flatpickr::make('period')
                    ->id('date-select')
                    ->label('Periodo')
                    ->default(now())
                    ->format('Y-m-d')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, ?Payroll $record) => $rule
                            ->unless(
                                is_null($record),
                                fn (Unique $rule) => $rule
                                    ->where('company_id', $record->company_id)
                                    ->where('type', SalaryTypeEnum::BIWEEKLY)
                            )
                    )
                    ->visible(fn (Get $get) => $get('type')?->isBiweekly())
                    ->disabled(fn (Get $get) => $get('type')?->isNotBiweekly())
                    ->displayFormat('d-m-Y')
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

    protected function configureForm(): void
    {
        $this->form
            ->components(function (Payroll $record) {
                $this->setPayroll($record);
                return[
                    $this->payrollSection(),
                    Section::make()
                        ->visibleOn(Operation::View)
                        ->schema([
                            Repeater::make('details')
                                ->hiddenLabel()
                                ->disabledOn([Operation::Create, Operation::Edit])
                                ->relationship()
                                ->columns(5)
                                ->deleteAction(
                                    fn (Action $action) => $action
                                        ->button()
                                        ->label('Remover empleado')
                                )
                                ->itemLabel(fn (array $state, Payroll $record) => $record->employees->findOrFail($state['employee_id'])->full_name)
                                ->schema([
                                    KeyValueEntry::make('information')
                                        ->hiddenLabel()
                                        ->columnSpan(1)
                                        ->state(fn (PayrollDetail $record) => [
                                            'Salario' => Number::currency($record->salary->amount),
                                            'Ingresos' => Number::currency($record->display->incomeTotal),
                                            'Deducciones' => Number::currency($record->display->deductionTotal),
                                            'Total a pagar' => Number::currency($record->display->netSalary),
                                        ]),
                                    Repeater::make('salaryAdjustments')
                                        ->relationship()
                                        ->grid(3)
                                        ->hiddenLabel()
                                        ->columnSpan(4)
                                        ->itemLabel(fn (array $state) => $state['name'])
                                        ->addAction(
                                            fn (Action $action, Get $get) => $action
                                                ->label('Agregar Ajuste Salarial')
                                                ->modalHeading('Agregar Ajuste Salarial')
                                                ->modalDescription('Seleccione un ajuste salarial de la nómina para agregar.')
                                                ->modalSubmitActionLabel('Agregar')
                                                ->icon(Heroicon::Plus)
                                                ->schema([
                                                    CheckboxList::make('available_salary_adjustments')
                                                        ->hiddenLabel()
                                                        ->options($this->payroll->salaryAdjustments->pluck('name', 'id'))
                                                        ->default(fn (PayrollDetail $record) => $record->salaryAdjustments->pluck('id')->toArray())
                                                        ->columns(2)
                                                        ->bulkToggleable()
                                                        ->disableOptionWhen(function (PayrollDetail $record, string $value) {
                                                            $allAdjustments = $this->payroll->monthlyPayroll?->salaryAdjustments->pluck('name', 'id');
                                                            if (is_null($allAdjustments)) {
                                                                return false;
                                                            }
                                                            $missingInComplementary = $allAdjustments->except($record->complementaryDetail?->salaryAdjustments->pluck('id'))->keys();
                                                            return $missingInComplementary->contains($value);
                                                        }),
                                                ])
                                                ->successNotification(
                                                    Notification::make('edit_available_adjustments')
                                                        ->title('Datos guardados')
                                                        ->success()
                                                )
                                                ->action(function (array $data, Repeater $component, Action $action) {
                                                    $livewire = $component->getContainer()->getLivewire();
                                                    $statePath = $component->getStatePath();

                                                    $pathParts = explode('.', $statePath);
                                                    $detailIndex = array_find($pathParts, fn (string $part) => str_contains($part, 'record-'));

                                                    $payrollDetailId = $livewire->data['details'][$detailIndex]['id'];
                                                    $payrollDetail = PayrollDetail::query()->findOrFail($payrollDetailId);

                                                    $payrollDetail->salaryAdjustments()->sync($data['available_salary_adjustments']);
                                                    return $action->sendSuccessNotification();
                                                })
                                        )
                                        ->schema([
                                            TextInput::make('custom_value')
                                                ->hiddenLabel()
                                                ->afterLabel(function (?SalaryAdjustment $record, ?array $state) {
                                                    if ($record) {
                                                        return $record->type->getLabel();
                                                    }

                                                    // For new items, try to get the salary adjustment from the state
                                                    if (isset($state['salary_adjustment_id'])) {
                                                        $adjustment = SalaryAdjustment::find($state['salary_adjustment_id']);
                                                        return $adjustment?->type->getLabel() ?? 'Ajuste';
                                                    }

                                                    return 'Ajuste';
                                                })
                                                ->numeric()
                                                ->step(0.01)
                                                ->placeholder('Ingrese el valor')
                                                ->statePath('detailSalaryAdjustmentValue.custom_value'),
                                        ])
                                ])
                        ])
                ];
            });
    }

    protected function setPayroll(Payroll $payroll): void
    {
        $this->payroll = $payroll;
    }

    public static function configure(Schema $schema): Schema
    {
        return new self($schema)->schema();
    }
}
