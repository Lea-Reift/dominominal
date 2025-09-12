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
use Filament\Schemas\Components\Section;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                Section::make()
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
                    ])
            );
    }
}
