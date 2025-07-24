<?php

declare(strict_types=1);

namespace App\Concerns;

use Filament\Forms\Components\Select;
use App\Enums\SalaryDistributionFormatEnum;
use Filament\Forms\Components\TextInput;
use App\Enums\DocumentTypeEnum;
use App\Enums\SalaryTypeEnum;
use Filament\Forms\Components\Fieldset;
use Filament\Support\RawJs;
use App\Forms\Components\PhoneRepeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Components\ToggleButtons;

trait HasEmployeeForm
{
    public function fields(bool $enabled = false, bool $nested = false): array
    {
        $salaryFieldNames = [
            'amount',
            'distribution_format',
            'distribution_value',
            'salary_type',
        ];

        $salaryFieldNames = array_combine($salaryFieldNames, $salaryFieldNames);

        if ($nested) {
            $salaryFieldNames = array_map(array: $salaryFieldNames, callback: fn (string $fieldName) => "salary.{$fieldName}");
        }

        $salaryDistribution = [
            ToggleButtons::make($salaryFieldNames['distribution_format'])
                ->options(SalaryDistributionFormatEnum::class)
                ->label('Formato')
                ->inline()
                ->live()
                ->required($enabled)
                ->default(SalaryDistributionFormatEnum::PERCENTAGE->value)
                ->helperText(fn (?string $state) => match (SalaryDistributionFormatEnum::tryFrom(intval($state))) {
                    SalaryDistributionFormatEnum::ABSOLUTE => 'El valor ingresado será restado del total del salario',
                    SalaryDistributionFormatEnum::PERCENTAGE => 'El valor ingresado se calculará al total del salario',
                    default => '',
                }),
            TextInput::make($salaryFieldNames['distribution_value'])
                ->label('Valor a restar de la primera quincena')
                ->numeric()
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->inputMode('decimal')
                ->default('50.00')
                ->required($enabled)
                ->prefix(fn (Get $get) => match (SalaryDistributionFormatEnum::tryFrom(intval($get($salaryFieldNames['distribution_format'])))) {
                    SalaryDistributionFormatEnum::ABSOLUTE => '0.0',
                    SalaryDistributionFormatEnum::PERCENTAGE => '%',
                    default => '',
                })
                ->maxValue(fn (Get $get) => match (SalaryDistributionFormatEnum::tryFrom(intval($get($salaryFieldNames['distribution_format'])))) {
                    SalaryDistributionFormatEnum::ABSOLUTE => match (true) {
                        is_float($get($salaryFieldNames['amount'])), is_int($get($salaryFieldNames['amount'])) => $get($salaryFieldNames['amount']),
                        is_string($get($salaryFieldNames['amount'])) => (float)str_replace(',', '', $get($salaryFieldNames['amount'])),
                        default => 0
                    },
                    SalaryDistributionFormatEnum::PERCENTAGE => 100,
                    default => PHP_INT_MAX,
                })
                ->helperText('El valor restante será usado en la segunda quincena')
        ];

        return [
            TextInput::make('name')
                ->label('Nombres')
                ->required($enabled)
                ->maxLength(255),
            TextInput::make('surname')
                ->label('Apellidos')
                ->required($enabled)
                ->maxLength(255),
            Select::make('document_type')
                ->label('Tipo de documento')
                ->options(DocumentTypeEnum::class)
                ->required($enabled)
                ->placeholder(null)
                ->live(),
            TextInput::make('document_number')
                ->label('Número de documento')
                ->required($enabled)
                ->mask(fn (Get $get) => DocumentTypeEnum::tryFrom((int) $get('document_type'))?->getMask())
                ->maxLength(255),
            TextInput::make('address')
                ->label('Dirección')
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->maxLength(255),

            Grid::make(3)
                ->schema([
                    Fieldset::make('Salario')
                        ->columnSpan(2)
                        ->unless($nested, fn (Fieldset $fieldset) => $fieldset->relationship('salary'))
                        ->schema([
                            TextInput::make($salaryFieldNames['amount'])
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->numeric()
                                ->required($enabled)
                                ->inputMode('decimal')
                                ->minValue(0)
                                ->live()
                                ->label('Valor'),
                            ToggleButtons::make($salaryFieldNames['salary_type'])
                                ->options(SalaryTypeEnum::class)
                                ->label('Tipo de salario')
                                ->helperText('Esto define la cantidad de pagos al mes que recibe el empleado')
                                ->inline()
                                ->live()
                                ->required($enabled)
                                ->default(SalaryTypeEnum::BIWEEKLY->value),
                            Section::make('Distribución Salarial')
                                ->description('Esta configuración se utiliza en las nominas quincenales para distribuir el salario del empleado')
                                ->compact()
                                ->columns(2)
                                ->hidden(fn (Get $get) => (int)$get($salaryFieldNames['salary_type']) === SalaryTypeEnum::MONTHLY->value)
                                ->schema($salaryDistribution),
                        ]),
                    PhoneRepeater::make('phones')
                        ->defaultItems(0)
                        ->columnSpan(1),
                ])
        ];
    }
}
