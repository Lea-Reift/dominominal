<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\PayrollResource\Pages;

use App\Enums\PayrollTypeEnum;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Resources\CompanyResource;
use App\Modules\Company\Resources\CompanyResource\Pages\ViewCompany;
use App\Modules\Payroll\Resources\PayrollResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Number;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Support\ValueObjects\PayrollDisplay\DetailDisplay;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Illuminate\Database\Query\Builder;
use App\Modules\Payroll\Exceptions\DuplicatedPayrollException;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Excel;
use Filament\Actions\Action as BaseAction;
use App\Modules\Payroll\Exports\PayrollExport;
use App\Tables\Columns\SalaryAdjustmentColumn;
use Filament\Actions\EditAction;
use Filament\Forms\Form;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;

/**
 * @property Payroll $record
 */
class ManageCompanyPayrollDetails extends ManageRelatedRecords
{
    protected static string $resource = PayrollResource::class;

    protected static string $relationship = 'details';

    protected static ?string $modelLabel = 'registro';
    protected static ?string $title = 'Registro';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected function resolveRecord(int | string $key): Payroll
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

        $period = $connector . ' ' . str($this->record->period->translatedFormat($format))->headline();

        return "Detalles de la nómina {$period} de {$this->record->company->name}";
    }

    public function getTitle(): string
    {
        return "Nómina #{$this->record->id}";
    }

    public function table(Table $table): Table
    {
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
                            ->options(Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->translatedFormat("{$day} \\d\\e F")]))
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
                    ->label('Añadir empleados')
                    ->disabled(fn () => $this->record->company->employees()->count() === $this->record->employees()->count())
                    ->form([
                        CheckboxList::make('employees')
                            ->hiddenLabel()
                            ->options(
                                fn () => $this->record->company->employees()
                                    ->whereNotIn('id', $this->record->employees()->select('employees.id'))
                                    ->get()
                                    ->pluck('full_name', 'id')
                            )
                            ->bulkToggleable()
                            ->searchable()
                            ->columns(2)
                            ->required()
                            ->columnSpanFull()
                    ])
                    ->action(function (array $data) {
                        $this->record->details()->saveMany(Employee::query()->whereIn('id', $data['employees'])->with('salary')->get()
                            ->map(fn (Employee $employee) => new PayrollDetail([
                                'employee_id' => $employee->id,
                                'salary_id' => $employee->salary->id,
                            ])));

                        Notification::make()
                            ->success()
                            ->title('Empleados agregados con éxito')
                            ->send();
                    }),
            ]);

        $columns = collect([
            TableGrid::make(10)
                ->schema([
                    Split::make([
                        Stack::make([
                            TextColumn::make('employee.full_name'),
                            TextColumn::make('salary')
                                ->formatStateUsing(fn (PayrollDetail $record) => 'Salario: ' . Number::currency($record->getParsedPayrollSalary()))
                                ->summarize(
                                    Summarizer::make()
                                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('rawSalary'))
                                        ->money()
                                        ->label('Total Salarios')
                                ),
                            TextColumn::make('incomes')
                                ->money()
                                ->state(fn (PayrollDetail $record) => 'Ingresos: ' . Number::currency((new DetailDisplay($record))->incomeTotal))
                                ->summarize(
                                    Summarizer::make()
                                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('incomeTotal'))
                                        ->money()
                                        ->label('Total Ingresos')
                                ),
                            TextColumn::make('deductions')
                                ->money()
                                ->state(fn (PayrollDetail $record) => 'Deducciones: ' . Number::currency((new DetailDisplay($record))->deductionTotal))
                                ->summarize(
                                    Summarizer::make()
                                        ->using(fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)->asDisplay()->sum('deductionTotal'))
                                        ->money()
                                        ->label('Total Deducciones')
                                ),
                            TextColumn::make('salaryAdjustments')
                                ->money()
                                ->state(fn (PayrollDetail $record) => 'Total a Pagar: ' . Number::currency((new DetailDisplay($record))->netSalary)),
                        ])

                    ])
                        ->extraAttributes(merge: true, attributes: [
                            'x-tooltip' => '{content: "El salario, los ingresos y las deducciones se calculan en base a los datos del empleado y la información de la nomina.",theme: $store.theme,}',
                        ])
                        ->columnSpan(2),
                    Split::make([
                        TableGrid::make(4)
                            ->schema(
                                $this->record->salaryAdjustments
                                    ->filter(fn (SalaryAdjustment $salaryAdjustment) => $salaryAdjustment->requires_custom_value)
                                    ->map(fn (SalaryAdjustment $adjustment) => SalaryAdjustmentColumn::make("salaryAdjustments.{$adjustment->id}.{$this->record->id}"))
                                    ->toArray()
                            )
                    ])
                        ->columnSpan(8),
                ]),

        ]);

        return $table
            ->columns($columns->toArray());
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
                        'payroll_id' => $payroll->id
                    ])
            )->save();

            $newDetail->salaryAdjustments()->sync($detail->salaryAdjustments);
        }
    }
}
