<?php

declare(strict_types=1);

namespace App\Concerns;

use Filament\Forms\Components\Select;
use App\Enums\SalaryDistributionFormatEnum;
use Filament\Forms\Components\TextInput;
use App\Enums\DocumentTypeEnum;
use App\Enums\SalaryTypeEnum;
use Filament\Support\RawJs;
use App\Forms\Components\PhoneRepeater;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Support\Number;

trait HasEmployeeForm
{
    public function fields(bool $enabled = false, bool $nested = false): array
    {
        $salaryDistribution = [
            ToggleButtons::make('distribution_format')
                ->options(SalaryDistributionFormatEnum::class)
                ->label('Formato')
                ->inline()
                ->live()
                ->required($enabled)
                ->default(SalaryDistributionFormatEnum::PERCENTAGE)
                ->helperText(fn (SalaryDistributionFormatEnum $state) => match ($state) {
                    SalaryDistributionFormatEnum::ABSOLUTE => 'El valor ingresado será restado del total del salario',
                    SalaryDistributionFormatEnum::PERCENTAGE => 'El valor ingresado se calculará al total del salario',
                }),
            TextInput::make('distribution_value')
                ->label('Valor a restar de la primera quincena')
                ->numeric()
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->inputMode('decimal')
                ->default('50.00')
                ->required($enabled)
                ->prefix(fn (Get $get) => match ($get('distribution_format')) {
                    SalaryDistributionFormatEnum::PERCENTAGE => '%',
                    default => '0.0',
                })
                ->maxValue(fn (Get $get) => match ($get('distribution_format')) {
                    SalaryDistributionFormatEnum::ABSOLUTE => match (true) {
                        is_float($get('amount')), is_int($get('amount')) => $get('amount'),
                        is_string($get('amount')) => (float)str_replace(',', '', $get('amount')),
                        default => 0
                    },
                    SalaryDistributionFormatEnum::PERCENTAGE => 100,
                    default => PHP_INT_MAX,
                })
                ->helperText('El valor restante será usado en la segunda quincena')
        ];

        return [
            Section::make()
                ->columns(3)
                ->contained(false)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombres')
                        ->required($enabled)
                        ->maxLength(255),
                    TextInput::make('surname')
                        ->label('Apellidos')
                        ->required($enabled)
                        ->maxLength(255),
                    TextInput::make('job_title')
                        ->label('Cargo')
                        ->maxLength(255),
                ]),
            Select::make('document_type')
                ->label('Tipo de documento')
                ->options(DocumentTypeEnum::class)
                ->required($enabled)
                ->placeholder(null)
                ->live(),
            TextInput::make('document_number')
                ->label('Número de documento')
                ->required($enabled)
                ->mask(fn (Get $get) => $get('document_type')?->getMask())
                ->maxLength(255),
            TextInput::make('address')
                ->label('Dirección')
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->maxLength(255),
            Section::make()
                ->columns(3)
                ->contained(false)
                ->schema([
                    Repeater::make('salaries')
                        ->relationship('salaries')
                        ->columnSpan(2)
                        ->collapsed()
                        ->itemLabel(fn (array $state) => Number::dominicanCurrency(parse_float((string)$state['amount'])))
                        ->label('Salarios')
                        ->addActionLabel('Agregar Salario')
                        ->schema([
                            TextInput::make('amount')
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->numeric()
                                ->required($enabled)
                                ->inputMode('decimal')
                                ->minValue(0)
                                ->live()
                                ->label('Valor'),
                            ToggleButtons::make('type')
                                ->options(SalaryTypeEnum::class)
                                ->label('Tipo de salario')
                                ->helperText('Esto define la cantidad de pagos al mes que recibe el empleado')
                                ->inline()
                                ->live()
                                ->required($enabled)
                                ->default(SalaryTypeEnum::BIWEEKLY),
                            Section::make('Distribución Salarial')
                                ->description('Esta configuración se utiliza en las nominas quincenales para distribuir el salario del empleado')
                                ->compact()
                                ->columns(2)
                                ->hidden(fn (Get $get) => $get('type') === SalaryTypeEnum::MONTHLY)
                                ->schema($salaryDistribution),
                        ]),
                    PhoneRepeater::make('phones')
                        ->defaultItems(0)
                        ->columnSpan(1),
                ])
        ];
    }
}
