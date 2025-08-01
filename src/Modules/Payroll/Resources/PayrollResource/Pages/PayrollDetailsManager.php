<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\PayrollResource\Pages;

use App\Concerns\HasEmployeeForm;
use App\Modules\Company\Resources\CompanyResource;
use App\Modules\Company\Resources\CompanyResource\Pages\ViewCompany;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\Resources\PayrollResource;
use App\Tables\Columns\SalaryAdjustmentColumn;
use Filament\Actions\Action as BaseAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Actions\ActionGroup as TableActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Number;
use Filament\Tables\Enums\ActionsPosition;
use App\Modules\Payroll\Actions\HeaderActions\EditPayrollAction;
use App\Modules\Payroll\Actions\TableActions\AddEmployeeAction;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use App\Modules\Payroll\Actions\TableActions\GenerateSecondaryPayrollsAction;
use App\Modules\Payroll\Actions\TableRowActions\EditAvailableAdjustmentsAction;
use App\Modules\Payroll\Actions\TableRowActions\ShowPaymentVoucherAction;
use App\Modules\Payroll\Resources\PayrollResource\Widgets\PayrollDetailAmountWidget;
use Closure;
use App\Modules\Payroll\Exports\PayrollExport;
use Maatwebsite\Excel\Excel;

/**
 * @property Payroll $record
 * @property Collection<int, salaryAdjustment> $editableAdjustments
 */
class PayrollDetailsManager extends ManageRelatedRecords
{
    use HasEmployeeForm;

    protected static string $resource = PayrollResource::class;
    protected static string $relationship = 'details';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public function mount(int|string $record): void
    {
        parent::mount($record);
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

    public function getTitle(): string
    {
        return "Nómina {$this->getHeading()} de {$this->record->company->name}";
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
        $format = $this->record->type->isMonthly()
            ? 'F \d\e\l Y'
            : 'd \d\e F \d\e\l Y';

        return Str::headline($this->record->period->translatedFormat($format));
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PayrollDetailAmountWidget::make([
                'totalRowDisplay' => (array)$this->record->display->totals,
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditPayrollAction::make(),
            ActionGroup::make([])
                ->hiddenLabel(false)
                ->button()
                ->label('Exportar')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->actions([
                    BaseAction::make('excel_export')
                        ->label('Exportar a Excel')
                        ->action(function () {
                            $payroll = $this->record;
                            $filenameDate = $payroll->period;

                            $filenameDate = match (true) {
                                $payroll->type->isMonthly() => $filenameDate->format('m-Y'),
                                default => $filenameDate->toDateString()
                            };

                            return (new PayrollExport($payroll->display))
                                ->download("Nómina Administrativa {$payroll->company->name} {$filenameDate}.xlsx", Excel::XLSX);
                        }),
                    BaseAction::make('pdf_export')
                        ->label('Exportar a PDF')
                        ->url(fn () => $this->getUrl(['record' => $this->record->id]) . '/export/pdf')
                        ->openUrlInNewTab(),
                ]),
        ];
    }


    public function table(Table $table): Table
    {
        $table
            ->paginated(false)
            ->headerActions([
                GenerateSecondaryPayrollsAction::make($this->record),
                AddEmployeeAction::make($this->record),
            ])
            ->actionsPosition(ActionsPosition::BeforeColumns)
            ->actions(TableActionGroup::make([])
                ->button()
                ->label('Opciones')
                ->actions([
                    EditAvailableAdjustmentsAction::make($this->record),
                    ShowPaymentVoucherAction::make($this->record),
                    DeleteAction::make()
                        ->modalHeading(fn (PayrollDetail $record) => "Eliminar a {$record->employee->full_name} de la nómina"),
                ]));

        $regularColumns = $this->defaultColumnsSchema();

        $columns = collect([
            TableGrid::make(10)
                ->schema([
                    Split::make([])
                        ->schema($regularColumns)
                        ->extraAttributes([
                            'x-tooltip' => '{content: "El salario, los ingresos y las deducciones se calculan en base a los datos del empleado y la información de la nomina.",theme: $store.theme,}',
                        ])
                        ->columnSpan(2),
                    Split::make([])
                        ->schema(Closure::fromCallable([$this, 'adjustmentsColumnsSchema']))
                        ->columnSpan(8),
                ]),

        ]);

        return $table
            ->columns($this->record->salaryAdjustments->isEmpty() ? $regularColumns : $columns->toArray());
    }

    protected function defaultColumnsSchema(): array
    {
        $hasAdjustments = $this->record->salaryAdjustments->isNotEmpty();

        $columns = [
            TextColumn::make('employee.full_name')
                ->label('Empleado'),
            TextColumn::make('salary')
                ->label('Salario')
                ->state(fn (PayrollDetail $record) => 'Salario: ' . Number::currency($record->getParsedPayrollSalary()))
                ->summarize(
                    Summarizer::make()
                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('rawSalary'))
                        ->money()
                        ->label('Total Salarios')
                ),
            TextColumn::make('incomes')
                ->label('Ingresos')
                ->state(fn (PayrollDetail $record) => ($hasAdjustments ? 'Ingresos: ' : '') . Number::currency($record->display->incomeTotal))
                ->summarize(
                    Summarizer::make()
                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('incomeTotal'))
                        ->money()
                        ->label('Total Ingresos')
                ),
            TextColumn::make('deductions')
                ->label('Deducciones')
                ->state(fn (PayrollDetail $record) => ($hasAdjustments ? 'Deducciones: ' : '') . Number::currency($record->display->deductionTotal))
                ->summarize(
                    Summarizer::make()
                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('deductionTotal'))
                        ->money()
                        ->label('Total Deducciones')
                ),
            TextColumn::make('salaryAdjustments')
                ->label('Total a pagar')
                ->state(
                    fn (PayrollDetail $record) => ($hasAdjustments ? 'Total a Pagar: ' : '') . Number::currency($record->display->netSalary)
                ),
        ];

        return $hasAdjustments ? [Stack::make($columns)] : $columns;
    }

    protected function adjustmentsColumnsSchema(?PayrollDetail $record): array
    {
        $adjustments = $record?->editableSalaryAdjustments->isNotEmpty()
            ? $record->editableSalaryAdjustments
            : $this->record->editableSalaryAdjustments;

        return [
            TableGrid::make()
                ->columns([
                    'sm' => 2,
                    'xl' => 3,
                    '2xl' => 4,
                ])
                ->schema(
                    SalaryAdjustmentColumn::fromIterable(
                        $adjustments->sortBy(fn (SalaryAdjustment $adjustment) => $adjustment->type->value)->pluck('id'),
                        $this->record
                    )
                ),
        ];
    }
}
