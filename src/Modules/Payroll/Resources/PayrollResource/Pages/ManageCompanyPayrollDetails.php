<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\PayrollResource\Pages;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Resources\CompanyResource;
use App\Modules\Company\Resources\CompanyResource\Pages\ViewCompany;
use App\Modules\Payroll\Resources\PayrollResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Number;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Fieldset;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Grid;
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
        return Payroll::with(['salaryAdjustments', 'incomes', 'deductions'])->findOrFail($key);
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

    public function getTitle(): string
    {
        $period = $this->record->type->isMonthly()
            ? 'de ' . ucfirst($this->record->period->translatedFormat('F-Y'))
            : 'al ' . $this->record->period->translatedFormat('d \d\e F \d\e\l Y');
        return "Detalles de la nómina {$period} de {$this->record->company->name}";
    }

    public function table(Table $table): Table
    {
        $inputs = $this->record->salaryAdjustments
            ->keyBy('id')
            ->map(fn (SalaryAdjustment $adjustment) => match ($adjustment->requires_custom_value) {

                true => TextInput::make("{$adjustment->type->getKey()}.{$adjustment->id}")
                    ->label(Str::headline($adjustment->name))
                    ->default(0)
                    ->placeholder($adjustment->value),

                false => Placeholder::make("{$adjustment->type->getKey()}.{$adjustment->id}")
                    ->label(Str::headline($adjustment->name))
                    ->content("{$adjustment->value_type->getLabel()}: " . match ($adjustment->value_type) {
                        SalaryAdjustmentValueTypeEnum::ABSOLUTE => Number::currency((float)$adjustment->value, 'DOP'),
                        SalaryAdjustmentValueTypeEnum::PERCENTAGE => "{$adjustment->value}%",
                        default => $adjustment->value
                    }),
            });

        $tabs = $this->record->salaryAdjustments
            ->groupBy('type')
            ->mapWithKeys(fn (EloquentCollection $adjustments, int $type) => [
                Str::plural(SalaryAdjustmentTypeEnum::from($type)->getLabel()) => $adjustments->pluck('id'),
            ])
            // @phpstan-ignore-next-line
            ->map(fn (Collection $adjustments) => $adjustments->map(fn (int $adjustmentId) => $inputs->get($adjustmentId)))
            ->map(
                fn (Collection $adjustmentsInputs, string $typeLabel) =>
                Fieldset::make($typeLabel)
                    ->schema($adjustmentsInputs->toArray())
                    ->columns(1)
                    ->columnSpan(1)
            );

        return $table
            ->recordTitleAttribute('employee_id')
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Stack::make([
                    TextColumn::make('employee.full_name'),
                    TextColumn::make('salary.amount')
                        ->money()
                        ->summarize(
                            Sum::make()
                                ->money()
                                ->label('Total Salarios')
                        ),
                ])
            ])
            ->headerActions([
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
            ])
            ->actions([
                Action::make('adjustments')
                    ->label('')
                    ->fillForm(
                        fn (PayrollDetail $record) => $record->salaryAdjustments
                            ->groupBy(fn (SalaryAdjustment $adjustment) => $adjustment->type->getKey())
                            ->map(fn (Collection $adjustments) => $adjustments->mapWithKeys(
                                fn (SalaryAdjustment $adjustment) =>
                                [$adjustment->id => $adjustment->detailSalaryAdjustmentValue->custom_value]
                            ))
                            ->toArray()
                    )
                    ->form([
                        Grid::make(2)
                            ->schema($tabs->toArray())
                    ])
                    ->action(function (array $data, PayrollDetail $record) {
                        $data = collect($data[SalaryAdjustmentTypeEnum::INCOME->getKey()] + $data[SalaryAdjustmentTypeEnum::DEDUCTION->getKey()])
                            ->mapWithKeys(fn (string $customValue, $adjustmentId) => [$adjustmentId => ['custom_value' => $customValue]]);
                        $record->salaryAdjustments()->sync($data);
                        Notification::make()
                            ->success()
                            ->title('Ajustes modificados con éxito')
                            ->send();
                    })
            ])
            ->recordAction('adjustments');
    }
}
