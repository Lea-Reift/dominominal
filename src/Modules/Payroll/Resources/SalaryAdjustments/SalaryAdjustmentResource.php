<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\SalaryAdjustments;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\EditAction;
use App\Modules\Payroll\Resources\SalaryAdjustments\Pages\ManageSalaryAdjustments;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Number;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class SalaryAdjustmentResource extends Resource
{
    protected static ?string $model = SalaryAdjustment::class;

    protected static ?string $modelLabel = 'ajuste salarial';

    protected static ?string $pluralModelLabel = 'ajustes salariales';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Configuración';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options(SalaryAdjustmentTypeEnum::class)
                    ->native(false)
                    ->required(),
                Select::make('value_type')
                    ->label('Tipo de valor')
                    ->options(SalaryAdjustmentValueTypeEnum::class)
                    ->native(false)
                    ->live()
                    ->required(),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->live(debounce: 1000, condition: fn (string $operation) => $operation === 'create')
                    ->afterStateUpdated(function (string $operation, mixed $state, callable $set) {
                        if ($operation === 'create') {
                            $set('parser_alias', str($state)->slug('_')->upper());
                        }
                    })
                    ->formatStateUsing(fn (?string $state) => Str::headline($state))
                    ->maxLength(255),
                TextInput::make('parser_alias')
                    ->label('Nombre de variable')
                    ->helperText('Este sera el valor utilizado en las formulas de este y los demás ajustes')
                    ->required()
                    ->maxLength(255),
                Grid::make(4)
                    ->schema([
                        ToggleButtons::make('requires_custom_value')
                            ->label('¿Requiere valor modificado?')
                            ->helperText('El valor modificado se solicitará al momento de registrar el ajuste en una nómina')
                            ->live()
                            ->required(fn (Get $get) => !is_null($get('value_type')) && $get('value_type') !== SalaryAdjustmentValueTypeEnum::FORMULA->value)
                            ->visible(fn (Get $get) => !is_null($get('value_type')) && $get('value_type') !== SalaryAdjustmentValueTypeEnum::FORMULA->value)
                            ->disabled(fn (Get $get) => !is_null($get('value_type')) && $get('value_type') === SalaryAdjustmentValueTypeEnum::FORMULA->value)
                            ->required()
                            ->boolean()
                            ->grouped(),
                        ToggleButtons::make('ignore_in_deductions')
                            ->label('Ignorar en deducciones')
                            ->helperText('El valor se incluira en el calculo de deducciones del seguro social')
                            ->visible(fn (Get $get) => !is_null($get('value_type')) && $get('value_type') !== SalaryAdjustmentValueTypeEnum::FORMULA->value)
                            ->disabled(fn (Get $get) => !is_null($get('value_type')) && $get('value_type') === SalaryAdjustmentValueTypeEnum::FORMULA->value)
                            ->required()
                            ->boolean()
                            ->grouped(),
                        ToggleButtons::make('is_absolute_adjustment')
                            ->label('¿Es un ajuste absoluto?')
                            ->helperText('Los ajustes absolutos se obtienen de la nómina mensual (si existe)')
                            ->required()
                            ->boolean()
                            ->grouped(),
                        ToggleButtons::make('ignore_in_isr')
                            ->label('¿Se debe restar del salario total para el ISR?')
                            ->required()
                            ->default(false)
                            ->boolean()
                            ->grouped(),
                    ]),
                Textarea::make('value')
                    ->label('Valor')
                    ->required(fn (Get $get) => !is_null($get('requires_custom_value')) && !((bool)$get('requires_custom_value')))
                    ->visible(fn (Get $get) => !is_null($get('requires_custom_value')) && !((bool)$get('requires_custom_value')))
                    ->disabled(fn (Get $get) => !is_null($get('requires_custom_value')) && ((bool)$get('requires_custom_value')))
                    ->columnSpanFull()
                    ->autosize(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre'),
                TextColumn::make('parser_alias')
                    ->tooltip('Este sera el valor utilizado en las formulas de este y los demás ajustes')
                    ->label('Nombre de variable')
                    ->hidden(),
                TextColumn::make('value_type')
                    ->label('Tipo de valor')
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Valor')
                    ->default('Modificable')
                    ->badge(fn (SalaryAdjustment $record) => $record->value_type === SalaryAdjustmentValueTypeEnum::FORMULA)
                    ->formatStateUsing(
                        fn (SalaryAdjustment $record, TextColumn $component) => isset($record->value)
                            ? match ($record->value_type) {
                                SalaryAdjustmentValueTypeEnum::ABSOLUTE => Number::currency((float) $record->value),
                                SalaryAdjustmentValueTypeEnum::PERCENTAGE => $record->value . '%',
                                SalaryAdjustmentValueTypeEnum::FORMULA => $record->value,
                            }
                        : $component->getDefaultState()
                    ),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalaryAdjustments::route('/'),
        ];
    }
}
