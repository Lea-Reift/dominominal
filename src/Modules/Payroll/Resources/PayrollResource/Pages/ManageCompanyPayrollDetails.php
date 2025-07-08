<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\PayrollResource\Pages;

use App\Concerns\HasEmployeeForm;
use App\Enums\PayrollTypeEnum;
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
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Exceptions\Halt;
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

/**
 * @property Payroll $record
 */
class ManageCompanyPayrollDetails extends ManageRelatedRecords
{
    use HasEmployeeForm;

    protected static string $resource = PayrollResource::class;

    protected string $formTabsId = 'add_employees_form_tabs';

    public string $importEmployeeTabId;

    public string $addEmployeeTabId;

    public string $activeFormTab;

    protected static string $relationship = 'details';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $title = 'Registro';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public function __construct()
    {
        $this->importEmployeeTabId = $this->formTabsId.'-import-employee-tab';
        $this->addEmployeeTabId = $this->formTabsId.'-add-employee-tab';
        $this->activeFormTab = $this->importEmployeeTabId;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
    }

    protected function resolveRecord(int|string $key): Payroll
    {
        return Payroll::query()
            ->with([
                'employees',
                'salaryAdjustments',
                'incomes',
                'deductions',
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

        $period = $connector.' '.str($this->record->period->translatedFormat($format))->headline();

        return "Detalles de la nómina {$period} de {$this->record->company->name}";
    }

    public function getTitle(): string
    {
        return "Nómina #{$this->record->id}";
    }

    public function table(Table $table): Table
    {
        $tabs = Tabs::make()
            ->id($this->formTabsId);

        $employeeListTab = Tab::make('import_employee')
            ->label('Importar Empleados')
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

        $table
            ->paginated(false)
            ->recordTitleAttribute('employee_id')
            ->headerActions([
                Action::make('secondary_payrolls')
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
                            ->required()
                            ->gridDirection('row')
                            ->columns(2)
                            ->options(Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->translatedFormat("{$day} \\d\\e F")])),
                    ])
                    ->color('success')
                    ->modalWidth(MaxWidth::Small)
                    ->modalFooterActionsAlignment(Alignment::Center)
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
                            ->body('nóminas generadas con éxito')
                            ->send();
                    }),
                Action::make('add_employees')
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
                    ->action(function (array $data, Action $action) {
                        $employee = Employee::query()->create([
                            ...$data,
                            'company_id' => $this->record->company_id
                        ]);

                        $employee->salary()->create($data['salary']);
                        if (isset($data['employees'])) {
                            $employees = Employee::query()->whereIn('id', $data['employees'])->with('salary')->get(['id', 'salary_id'])
                                ->map(fn (Employee $employee) => new PayrollDetail([
                                    'employee_id' => $employee->id,
                                    'salary_id' => $employee->salary->id,
                                ]));

                            $this->record->details()->saveMany($employees);
                        } else {
                            $this->record->details()->save(new PayrollDetail(['employee_id' => $employee->id, 'salary_id' => $employee->salary->id]));
                        }

                        return $action->sendSuccessNotification();
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Empleados agregados con éxito')
                    ),
            ])
            ->actions(
                position: ActionsPosition::BeforeColumns,
                actions:[
                    ActionGroup::make([
                        Action::make('show_payment_voucher')
                            ->label('Mostrar volante')
                            ->button()
                            ->icon('heroicon-s-inbox-arrow-down')
                            ->modalHeading(fn (PayrollDetail $record) => $record->employee->full_name)
                            ->color('info')
                            ->modalContent(fn (PayrollDetail $record) => view(
                                'show-pdf',
                                ['pdf_base64_string' => base64_encode($record->display->renderPDF())],
                            ))
                            ->modalSubmitAction(false)
                            ->size(ActionSize::ExtraSmall),
                        DeleteAction::make()
                            ->size(ActionSize::ExtraSmall)
                            ->modalHeading(fn (PayrollDetail $record) => "Eliminar a {$record->employee->full_name} de la nómina")
                            ->button()
                    ])
                        ->button()
                        ->size(ActionSize::Small)
                        ->label('Opciones')
                ]
            );

        $hasAdjustments = $this->record->salaryAdjustments->isNotEmpty();

        $regularColums = [
            TextColumn::make('employee.full_name')
                ->label('Empleado'),
            TextColumn::make('salary')
                ->label('Salario')
                ->formatStateUsing(fn (PayrollDetail $record) => 'Salario: '.Number::currency($record->getParsedPayrollSalary()))
                ->summarize(
                    Summarizer::make()
                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('rawSalary'))
                        ->money()
                        ->label('Total Salarios')
                ),
            TextColumn::make('incomes')
                ->label('Ingresos')
                ->money()
                ->state(fn (PayrollDetail $record) => ($hasAdjustments ? 'Ingresos: ' : '').Number::currency((new PayrollDetailDisplay($record))->incomeTotal))
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
                    Split::make([
                        TableGrid::make([
                            'sm' => $this->record->salaryAdjustments->count() / 4,
                            'lg' => ($this->record->salaryAdjustments->count() / 4) * 2,
                        ])
                            ->schema(
                                $this->record->salaryAdjustments
                                    ->filter(fn (SalaryAdjustment $salaryAdjustment) => $salaryAdjustment->requires_custom_value)
                                    ->map(fn (SalaryAdjustment $adjustment) => SalaryAdjustmentColumn::make("salaryAdjustments.{$adjustment->id}.{$this->record->id}"))
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
                'type' => PayrollTypeEnum::BIWEEKLY,
                'period' => $period,
            ]))
            ->save();

        // SalaryAdjustments
        $payroll->salaryAdjustments()->sync($this->record->salaryAdjustments);

        // Details
        foreach ($this->record->details as $detail) {
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
}
