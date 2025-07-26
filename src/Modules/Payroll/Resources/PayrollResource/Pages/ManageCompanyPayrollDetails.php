<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\PayrollResource\Pages;

use App\Concerns\HasEmployeeForm;
use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Resources\CompanyResource;
use App\Modules\Company\Resources\CompanyResource\Pages\ViewCompany;
use App\Modules\Payroll\Exceptions\DuplicatedPayrollException;
use App\Modules\Payroll\Exports\PayrollExport;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\Resources\PayrollResource;
use App\Support\ValueObjects\PayrollDisplay\PayrollDetailDisplay;
use App\Tables\Columns\SalaryAdjustmentColumn;
use Filament\Actions\Action as BaseAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use Maatwebsite\Excel\Excel;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentVoucherMail;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Tabs\Tab;

/**
 * @property Payroll $record
 * @property Collection<int, salaryAdjustment> $editableAdjustments
 */
class ManageCompanyPayrollDetails extends ManageRelatedRecords
{
    use HasEmployeeForm;

    protected static string $resource = PayrollResource::class;

    protected string $formTabsId = 'add_employees_form_tabs';

    public string $importEmployeeTabId;

    public string $addEmployeeTabId;

    public string $activeFormTab;

    public bool $showImportEmployeeTab = true;

    protected static string $relationship = 'details';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $title = 'Registro';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public function __construct()
    {
        $this->importEmployeeTabId = $this->formTabsId . '-import-employee-tab';
        $this->addEmployeeTabId = $this->formTabsId . '-add-employee-tab';
        $this->activeFormTab = $this->importEmployeeTabId;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->showImportEmployeeTab = $this->record->company->employees->reject(fn (Employee $employee) => $this->record->employees->contains($employee))->isNotEmpty();
    }

    protected function resolveRecord(int|string $key): Payroll
    {
        return Payroll::query()
            ->with([
                'company.employees',
                'employees',
                'salaryAdjustments',
                'editableSalaryAdjustments',
                'incomes',
                'deductions',
                'monthlyPayroll',
                'details' => [
                    'salaryAdjustments',
                ],
            ])
            ->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->form(fn (Form $form) => PayrollResource::form($form))
                ->databaseTransaction()
                ->after(function (Payroll $editedPayroll) {
                    if ($editedPayroll->type->isBiweekly()) {
                        $monthlyPayrollId = Payroll::query()
                            ->whereDate('period', $editedPayroll->period->setDay(match (true) {
                                $editedPayroll->period->month === 2 => 28,
                                default => 30,
                            }))
                            ->value('id');

                        $editedPayroll->update(['monthly_payroll_id' => $monthlyPayrollId]);
                        return;
                    }

                    $currentMontlyPayrollSalaryAdjustments = $editedPayroll->salaryAdjustments()->pluck('salary_adjustments.id')->toArray();

                    Payroll::query()
                        ->whereMonth('period', $editedPayroll->period->month)
                        ->whereYear('period', $editedPayroll->period->year)
                        ->where('id', '!=', $editedPayroll->id)
                        ->update([
                            'monthly_payroll_id' => $editedPayroll->id,
                        ]);

                    $editedPayroll->details()->get()->each( # @phpstan-ignore-next-line
                        fn (PayrollDetail $detail) => $this->updateDetailSalaryAdjustmentsForEntity($detail, $currentMontlyPayrollSalaryAdjustments)
                    );

                    $this->updateDetailSalaryAdjustmentsForEntity($editedPayroll, $currentMontlyPayrollSalaryAdjustments);

                    $biweeklyPayrolls = $editedPayroll->biweeklyPayrolls()->with('details')->get();

                    if ($biweeklyPayrolls->isEmpty()) {
                        return;
                    }

                    // @phpstan-ignore-next-line
                    $biweeklyPayrolls->each(function (Payroll $biweeklyPayroll) use ($currentMontlyPayrollSalaryAdjustments) {
                        $this->updateDetailSalaryAdjustmentsForEntity($biweeklyPayroll, $currentMontlyPayrollSalaryAdjustments);
                        $biweeklyPayroll->details->each(
                            // @phpstan-ignore-next-line
                            fn (PayrollDetail $detail) => $this->updateDetailSalaryAdjustmentsForEntity($detail, $currentMontlyPayrollSalaryAdjustments)
                        );
                    });
                })
                ->slideOver(),
            BaseAction::make('excel_export')
                ->label('Exportar a excel')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $filenameDate = $this->record->period;

                    $filenameDate = match (true) {
                        $this->record->type->isMonthly() => $filenameDate->format('m-Y'),
                        default => $filenameDate->toDateString()
                    };

                    return (new PayrollExport($this->record->display))
                        ->download("Nómina Administrativa {$this->record->company->name} {$filenameDate}.xlsx", Excel::XLSX);
                }),
            BaseAction::make('pdf_export')
                ->label('Exportar PDF')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $filenameDate = $this->record->period;

                    $filenameDate = match (true) {
                        $this->record->type->isMonthly() => $filenameDate->format('m-Y'),
                        default => $filenameDate->toDateString()
                    };

                    return (new PayrollExport($this->record->display))
                        ->download("Nómina Administrativa {$this->record->company->name} {$filenameDate}.pdf", Excel::DOMPDF);
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        $resource = $this->getResource();

        $breadcrumbs = [
            CompanyResource::getUrl() => CompanyResource::getBreadcrumb(),
            ViewCompany::getUrl(['record' => $this->record->company_id]) => $this->record->company->name,
            ViewCompany::getUrl(['record' => $this->record->company_id, 'activeRelationManager' => 'payrolls']) => $resource::getBreadcrumb(),
        ];

        return $breadcrumbs;
    }

    public function getHeading(): string
    {

        if ($this->record->type->isMonthly()) {
            $connector = 'de';
            $format = 'F \d\e\l Y';
        } else {
            $connector = 'al';
            $format = 'd \d\e F \d\e\l Y';
        }

        $period = $connector . ' ' . str($this->record->period->translatedFormat($format))->headline();

        return "Detalles de la nómina {$period} de {$this->record->company->name}";
    }

    public function getTitle(): string
    {
        return "Nómina #{$this->record->id}";
    }

    public function table(Table $table): Table
    {
        $disableSecondaryPayrolls =  Payroll::query()
            ->whereIn(
                'period',
                Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->setDay($day)->toDateString()])
            )
            ->exists();

        $table
            ->paginated(false)
            ->recordTitleAttribute('employee_id')
            ->headerActions([
                $this->secondaryPayrollsTableAction($disableSecondaryPayrolls),
                $this->addEmployeesTableAction(),
            ])
            ->actionsPosition(ActionsPosition::BeforeColumns)
            ->actions($this->getTableActions());

        $hasAdjustments = $this->record->salaryAdjustments->isNotEmpty();

        $regularColums = [
            TextColumn::make('employee.full_name')
                ->label('Empleado'),
            TextColumn::make('salary')
                ->label('Salario')
                ->formatStateUsing(fn (PayrollDetail $record) => 'Salario: ' . Number::currency($record->getParsedPayrollSalary()))
                ->summarize(
                    Summarizer::make()
                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('rawSalary'))
                        ->money()
                        ->label('Total Salarios')
                ),
            TextColumn::make('incomes')
                ->label('Ingresos')
                ->money()
                ->state(fn (PayrollDetail $record) => ($hasAdjustments ? 'Ingresos: ' : '') . Number::currency((new PayrollDetailDisplay($record))->incomeTotal))
                ->summarize(
                    Summarizer::make()
                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('incomeTotal'))
                        ->money()
                        ->label('Total Ingresos')
                ),
            TextColumn::make('deductions')
                ->label('Deducciones')
                ->money()
                ->state(fn (PayrollDetail $record) => ($hasAdjustments ? 'Deducciones: ' : '') . Number::currency((new PayrollDetailDisplay($record))->deductionTotal))
                ->summarize(
                    Summarizer::make()
                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('deductionTotal'))
                        ->money()
                        ->label('Total Deducciones')
                ),
            TextColumn::make('salaryAdjustments')
                ->label('Total a pagar')
                ->money()
                ->state(
                    fn (PayrollDetail $record) => ($hasAdjustments ? 'Total a Pagar: ' : '') . Number::currency((new PayrollDetailDisplay($record))->netSalary)
                ),
        ];

        $columns = collect([
            TableGrid::make(10)
                ->schema([
                    Split::make([Stack::make($regularColums)])
                        ->extraAttributes(merge: true, attributes: [
                            'x-tooltip' => '{content: "El salario, los ingresos y las deducciones se calculan en base a los datos del empleado y la información de la nomina.",theme: $store.theme,}',
                        ])
                        ->columnSpan(2),
                    Split::make([])
                        ->schema(fn (?PayrollDetail $record) => [
                            TableGrid::make()
                                ->columns([
                                    'sm' => 2,
                                    'xl' => 3,
                                    '2xl' => 4,
                                ])
                                ->schema(
                                    ($record->editableSalaryAdjustments ?? $this->record->editableSalaryAdjustments)
                                        ->sortBy('type')
                                        ->map(
                                            fn (SalaryAdjustment $adjustment) =>
                                            SalaryAdjustmentColumn::make("salaryAdjustments.{$adjustment->id}.{$this->record->id}")
                                        )
                                        ->toArray()
                                ),
                        ])
                        ->columnSpan(8),
                ]),

        ]);

        return $table
            ->columns($this->record->salaryAdjustments->isEmpty() ? $regularColums : $columns->toArray());
    }

    public function setCurrentFormTab(string $currentTab)
    {
        if (!$this->showImportEmployeeTab) {
            $this->activeFormTab = $this->addEmployeeTabId;
            return;
        }

        $tabsIndex = [
            $this->importEmployeeTabId => 0,
            $this->addEmployeeTabId => 1,
        ];

        if (is_numeric($currentTab)) {
            $currentTab = array_search($currentTab, $tabsIndex);
        }

        $this->activeFormTab = $currentTab;
    }

    public function generateSecondaryPayroll(int $day): void
    {
        $period = $this->record->period->clone()->setDay($day);

        throw_if(
            condition: Payroll::query()->where('period', $period)->exists(),
            exception: DuplicatedPayrollException::make($period)
        );

        /** @var Payroll $payroll */
        $payroll = tap($this->record->replicate()
            ->unsetRelations()
            ->fill([
                'type' => SalaryTypeEnum::BIWEEKLY,
                'period' => $period,
                'monthly_payroll_id' => $this->record->id,
            ]))
            ->save();

        // SalaryAdjustments
        $payroll->salaryAdjustments()->sync($this->record->salaryAdjustments);

        $details = $this->record->details
            ->when(
                $period->day === 14,
                fn (Collection $details) => $details->reject(
                    fn (PayrollDetail $detail) => $detail->salary->type->isMonthly()
                )
            );

        // Details
        foreach ($details as $detail) {
            /**
             * @var PayrollDetail $newDetail
             * @var PayrollDetail $detail
             */
            $newDetail = tap(
                $detail->replicate()
                    ->unsetRelations()
                    ->fill([
                        'payroll_id' => $payroll->id,
                    ])
            )->save();

            $newDetail->salaryAdjustments()->sync($detail->salaryAdjustments);
        }
    }

    public function updateDetailSalaryAdjustmentsForEntity(Payroll|PayrollDetail $entity, array $salaryAdjustmentsModification): void
    {
        $relation = $entity->salaryAdjustments();

        $currentPayrollSalaryAdjustments = $relation->pluck($relation->getRelatedPivotKeyName())->toArray();

        $salaryAdjustmentsToAdd = array_diff($salaryAdjustmentsModification, $currentPayrollSalaryAdjustments);
        $salaryAdjustmentsToRemove = array_diff($currentPayrollSalaryAdjustments, $salaryAdjustmentsModification);

        $relation->syncWithoutDetaching($salaryAdjustmentsToAdd);
        $relation->detach($salaryAdjustmentsToRemove);
    }

    protected function secondaryPayrollsTableAction(bool $disableSecondaryPayrolls): Action
    {
        return Action::make('secondary_payrolls')
            ->label('Generar nóminas secundarias')
            ->modalHeading('Generar nóminas al...')
            ->visible(fn () => $this->record->type->isMonthly())
            ->size(ActionSize::Small)
            ->modalIcon('heroicon-s-clipboard-document')
            ->databaseTransaction()
            ->form([
                CheckboxList::make('dates')
                    ->label('')
                    ->bulkToggleable()
                    ->hint(function () use ($disableSecondaryPayrolls) {
                        $hasEmployeesWithMonthlySalary = $this->record->employees
                            ->filter(fn (Employee $employee) => $employee->salary->type->isMonthly())
                            ->isNotEmpty();

                        return match (true) {
                            $disableSecondaryPayrolls => 'Las nóminas del mes ya fueron generadas',
                            $hasEmployeesWithMonthlySalary => 'Los empleados con salarios mensuales no aparecerán en nominas de la primera quincena del mes',
                            default => '',
                        };
                    })
                    ->hintColor('warning')
                    ->validationMessages([
                        'required' => 'Debe seleccionar al menos una fecha'
                    ])
                    ->required()
                    ->gridDirection('row')
                    ->default(
                        fn () => DB::query()
                            ->from($this->record->getTable())
                            ->select('period')
                            ->whereIn(
                                'period',
                                Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->setDay($day)->toDateString()])
                            )
                            ->pluck('period')
                            ->toArray()
                    )
                    ->columns(2)
                    ->dehydrated(!$disableSecondaryPayrolls)
                    ->disableOptionWhen(fn (string $value) => Payroll::query()->whereDate('period', $this->record->period->setDay(intval($value)))->exists())
                    ->options(Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->translatedFormat("{$day} \\d\\e F")])),
            ])
            ->color('success')
            ->modalWidth(MaxWidth::Small)
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalSubmitAction($disableSecondaryPayrolls ? false : null)
            ->action(function (array $data) {
                foreach ($data['dates'] as $day) {
                    try {
                        $this->generateSecondaryPayroll(intval($day));
                    } catch (DuplicatedPayrollException $e) {
                        Notification::make()
                            ->title('Nóminas Secundarias')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();

                        throw new Halt();
                    }
                }

                Notification::make()
                    ->title('Nóminas Secundarias')
                    ->success()
                    ->body('Nóminas generadas con éxito')
                    ->send();
            });
    }

    protected function addEmployeesTableAction(): Action
    {
        $action = function (array $data, Action $action) {
            $isRegisteringEmployee = empty($data['employees']);
            $payrollDetails = Collection::make()
                ->unless(
                    $isRegisteringEmployee,
                    fn (Collection $payrollDetails) => $payrollDetails
                        ->union(Employee::query()->whereIn('id', $data['employees'])->with('salary')->get(['id'])
                            ->map(fn (Employee $employee) => new PayrollDetail([
                                'employee_id' => $employee->id,
                                'salary_id' => $employee->salary->id,
                            ])))
                )
                ->when(
                    $isRegisteringEmployee,
                    function (Collection $payrollDetails) use ($data) {
                        $employee = Employee::query()->create([
                            ...$data,
                            'company_id' => $this->record->company_id
                        ]);

                        $employee->salary()->create($data['salary']);
                        $payrollDetails->push(new PayrollDetail(['employee_id' => $employee->id, 'salary_id' => $employee->salary->id]));
                    }
                );


            /** @var Collection<int, salaryAdjustment> $details */
            $details = $this->record->details()->saveMany($payrollDetails);

            $adjustmentsForDetails = $this->record->salaryAdjustments->mapWithKeys(fn (SalaryAdjustment $payrollAdjustment) => [
                $payrollAdjustment->id => ['custom_value' => null],
            ]);

            $details->each(fn (PayrollDetail $detail) => $detail->salaryAdjustments()->sync($adjustmentsForDetails));
            $this->mount($this->record->id);
            return $action->sendSuccessNotification();
        };

        $tabs = Tabs::make()
            ->id($this->formTabsId);

        $employeeListTab = Tab::make('import_employee')
            ->label('Importar Empleados')
            ->visible($this->showImportEmployeeTab)
            ->live()
            ->schema([
                CheckboxList::make('employees')
                    ->hiddenLabel()
                    ->dehydrated(fn (Component $component) => $component->getContainer()->getParentComponent()->isDisabled())
                    ->disabled(fn (Component $component) => $component->getContainer()->getParentComponent()->isDisabled())
                    ->options(
                        fn () => $this->record->company->employees()
                            ->whereNotIn('id', $this->record->employees()->select('employees.id'))
                            ->get()
                            ->pluck('full_name', 'id')
                    )
                    ->bulkToggleable()
                    ->searchable()
                    ->columns(2)
                    ->columnSpanFull(),
            ]);

        $employeeFormTab = Tab::make('add_employee')
            ->label('Crear Empleado')
            ->live()
            ->columns(2)
            ->schema(fn (Component $component) => $this->fields(enabled: ! $component->isDisabled(), nested: true));

        return Action::make('add_employees')
            ->mountUsing(function (Form $form) {
                $form->fill();
                $this->setCurrentFormTab('0');
            })
            ->modalHeading('')
            ->label('Añadir empleados')
            ->slideOver()
            ->form([
                $tabs
                    ->tabs([
                        $employeeListTab
                            ->disabled(fn () => $this->activeFormTab === $this->addEmployeeTabId)
                            ->dehydrated(fn () => $this->activeFormTab !== $this->addEmployeeTabId),
                        $employeeFormTab
                            ->disabled(fn () => $this->activeFormTab === $this->importEmployeeTabId)
                            ->dehydrated(fn () => $this->activeFormTab !== $this->importEmployeeTabId),
                    ])
                    ->extraAttributes([
                        'wire:click' => 'setCurrentFormTab(tab)',
                    ]),
            ])
            ->databaseTransaction()
            ->action($action)
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Empleados agregados con éxito')
            );
    }

    protected function getTableActions(): array
    {

        return [
            ActionGroup::make([])
                ->button()
                ->label('Opciones')
                ->actions([
                    Action::make('edit_available_adjustments')
                        ->disabled($this->record->type->isMonthly())
                        ->hidden($this->record->type->isMonthly())
                        ->label('Editar ajustes salariales')
                        ->icon('heroicon-m-pencil-square')
                        ->color('success')
                        ->modalWidth(MaxWidth::Large)
                        ->form([
                            CheckboxList::make('available_salary_adjustments')
                                ->hiddenLabel()
                                ->options($this->record->salaryAdjustments->pluck('name', 'id'))
                                ->default(fn (PayrollDetail $record) => $record->salaryAdjustments->pluck('id')->toArray())
                                ->columns(2)
                                ->bulkToggleable()
                                ->disableOptionWhen(function (PayrollDetail $record, string $value) {
                                    $allAdjustments = $this->record->salaryAdjustments->pluck('name', 'id');
                                    $missingInComplementary = $allAdjustments->except($record->complementaryDetail->salaryAdjustments->pluck('id'))->keys();
                                    return $missingInComplementary->contains($value);
                                })
                        ])
                        ->databaseTransaction()
                        ->action(function (array $data, PayrollDetail $record, Action $action) {
                            $record->salaryAdjustments()->sync($data['available_salary_adjustments']);
                            return $action->sendSuccessNotification();
                        })
                        ->successNotification(
                            Notification::make('edit_available_adjustments')
                                ->title('Datos guardados')
                                ->success()
                        ),
                    Action::make('show_payment_voucher')
                        ->label('Mostrar volante')
                        ->icon('heroicon-s-inbox-arrow-down')
                        ->modalHeading(fn (PayrollDetail $record) => $record->employee->full_name)
                        ->color('info')
                        ->modalContent(fn (PayrollDetail $record) => view(
                            'show-pdf',
                            ['pdf_base64_string' => base64_encode($record->display->renderPDF())],
                        ))
                        ->form([
                            TextInput::make('employee_email')
                                ->label('Correo del empleado')
                                ->email()
                                ->default(fn (PayrollDetail $record) => $record->employee->email)
                                ->required(),
                        ])
                        ->modalSubmitActionLabel('Enviar comprobante')
                        ->action(function (PayrollDetail $record, array $data) {
                            $employeeEmail = $record->employee->email ?? $data['employee_email'];
                            $pdfOutput = $record->display->renderPDF();

                            $mailSubject = "Volante de pago {$record->employee->full_name} {$record->payroll->period->format('d/m/Y')}";

                            $mail = new PaymentVoucherMail($mailSubject, $pdfOutput);

                            defer(fn () => Mail::to($employeeEmail)->send($mail));

                            Notification::make('send_payment_voucher')
                                ->title('Voucher enviado con exito')
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->modalHeading(fn (PayrollDetail $record) => "Eliminar a {$record->employee->full_name} de la nómina")
                        ->after(function () {
                            $this->mount($this->record->id);
                            $this->setCurrentFormTab($this->activeFormTab);
                        })
                ]),
        ];
    }
}
