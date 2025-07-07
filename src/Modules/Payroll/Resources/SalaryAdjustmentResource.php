<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Resources\SalaryAdjustmentResource\Pages;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Number;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class SalaryAdjustmentResource extends Resource
{
    protected static ?string $model = SalaryAdjustment::class;

    protected static ?string $modelLabel = 'ajuste salarial';

    protected static ?string $pluralModelLabel = 'ajustes salariales';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Configuración';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->live(debounce: 1000)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('parser_alias', str($state)->slug('_')->upper());
                    })
                    ->formatStateUsing(fn (?string $state) => Str::headline($state))
                    ->maxLength(255),
                TextInput::make('parser_alias')
                    ->label('Nombre de variable')
                    ->helperText('Este sera el valor utilizado en las formulas de este y los demás ajustes')
                    ->required()
                    ->maxLength(255),
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
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSalaryAdjustments::route('/'),
        ];
    }
}
