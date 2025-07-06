<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\CompanyResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use App\Enums\PayrollTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Get;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use App\Modules\Payroll\Resources\PayrollResource\Pages\ManageCompanyPayrollDetails;
use Filament\Tables\Grouping\Group;
use Illuminate\Validation\Rules\Unique;
use App\Modules\Company\Models\Company;

/**
 * @property Company $ownerRecord
 */
class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';
    protected static ?string $title = 'Nóminas';
    protected static ?string $modelLabel = 'nómina';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->label('Tipo')
                    ->required()
                    ->live()
                    ->default(PayrollTypeEnum::MONTHLY->value)
                    ->options(PayrollTypeEnum::class)
                    ->native(false),

                Flatpickr::make('period')
                    ->label('Periodo')
                    ->id('month-select')
                    ->format('Y-m-d')
                    ->monthPicker()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule
                            ->where('company_id', $this->ownerRecord->id)
                            ->where('type', PayrollTypeEnum::MONTHLY)
                    )
                    ->default(now())
                    ->visible(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) === PayrollTypeEnum::MONTHLY)
                    ->disabled(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) !== PayrollTypeEnum::MONTHLY)
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
                        modifyRuleUsing: fn (Unique $rule) => $rule
                            ->where('company_id', $this->ownerRecord->id)
                            ->where('type', PayrollTypeEnum::BIWEEKLY)
                    )
                    ->visible(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) === PayrollTypeEnum::BIWEEKLY)
                    ->disabled(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) !== PayrollTypeEnum::BIWEEKLY)
                    ->displayFormat('d-m-Y')
                    ->closeOnDateSelection()
                    ->required(),

                Fieldset::make('Ajustes Salariales')
                    ->schema([
                        CheckboxList::make('incomes')
                            ->label('Ingresos')
                            ->relationship('incomes', 'name')
                            ->bulkToggleable()
                            ->required()
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
                            ->required()
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period')
            ->recordUrl(fn (Payroll $record) => ManageCompanyPayrollDetails::getUrl(['record' => $record]))
            ->defaultGroup(
                Group::make('type')
                    ->titlePrefixedWithLabel(false)
                    ->getDescriptionFromRecordUsing(fn (Payroll $record): string => $record->type->getDescription()),
            )
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo'),
                TextColumn::make('period')
                    ->label('Periodo')
                    ->date(fn (Payroll $record) => $record->type->isMonthly() ? 'F-Y' : 'd-m-Y'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar nómina'),
            ]);
    }
}
