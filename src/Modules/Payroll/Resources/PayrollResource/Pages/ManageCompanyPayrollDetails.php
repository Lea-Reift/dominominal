<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\PayrollResource\Pages;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Resources\CompanyResource;
use App\Modules\Company\Resources\CompanyResource\Pages\ViewCompany;
use App\Modules\Payroll\Resources\PayrollResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;

/**
 * @property Payroll $record
 */
class ManageCompanyPayrollDetails extends ManageRelatedRecords
{
    protected static string $resource = PayrollResource::class;

    protected static string $relationship = 'details';

    protected static string $modelLabel = 'registro';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        $breadcrumbs = [
            CompanyResource::getUrl() => CompanyResource::getBreadcrumb(),
            ViewCompany::getUrl(['record' => $this->record->company_id]) => $this->record->company->name,
            $resource::getUrl() => $resource::getBreadcrumb(),
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->options(fn () => $this->record->company->employees()->select(['name', 'surname', 'id'])->get()->pluck('full_name', 'id'))
                    ->label('Empleado')
                    ->native(false)
                    ->required(),
                Select::make('salary_adjustments')
                    ->label('Ajustes Salariales')
                    ->multiple()
                    ->native(false)
                    ->required()
                    ->options(function () {
                        $adjustments = SalaryAdjustment::all(['id', 'name', 'type'])
                            ->groupBy('type')
                            ->mapWithKeys(fn (Collection $adjustmentByType, int $key) => [
                                SalaryAdjustmentTypeEnum::from($key)->getLabel() => $adjustmentByType->pluck('name', 'id'),
                            ]);
                        return $adjustments;
                    })

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(static::$modelLabel)
            ->recordTitleAttribute('employee_id')
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Empleado'),
                Tables\Columns\TextColumn::make('salary.amount')
                    ->label('Salario')
                    ->money('DOP'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modelLabel(static::$modelLabel)
                    ->mutateFormDataUsing(function (array $data) {
                        $data['salary_id'] = Employee::query()->with('salary')->findOrFail($data['employee_id'])->salary->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}
