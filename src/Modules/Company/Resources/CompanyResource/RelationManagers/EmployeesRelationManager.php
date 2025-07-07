<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\CompanyResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Enums\DocumentTypeEnum;
use Filament\Forms\Components\Fieldset;
use Filament\Support\RawJs;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Group;
use App\Enums\SalaryDistributionFormatEnum;
use Filament\Tables\Columns\TextColumn;
use App\Tables\Columns\DocumentColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Get;
use App\Forms\Components\PhoneRepeater;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';
    protected static ?string $title = 'Empleados';
    protected static ?string $modelLabel = 'empleado';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function fields(bool $enabled = false, bool $nested = false): array
    {
        $salaryFieldNames = [
            'amount',
            'modify_distribution',
            'distribution_format',
            'distribution_value',
        ];

        $salaryFieldNames = array_combine($salaryFieldNames, $salaryFieldNames);

        if ($nested) {
            $salaryFieldNames = array_map(array: $salaryFieldNames, callback: fn (string $fieldName) => "salary.{$fieldName}");
        }

        return [
            TextInput::make('name')
                ->label('Nombres')
                ->required($enabled)
                ->dehydrated($enabled)
                ->maxLength(255),
            TextInput::make('surname')
                ->label('Apellidos')
                ->required($enabled)
                ->dehydrated($enabled)
                ->maxLength(255),
            Select::make('document_type')
                ->label('Tipo de documento')
                ->native(false)
                ->options(DocumentTypeEnum::class)
                ->dehydrated($enabled)
                ->live()
                ->default(DocumentTypeEnum::IDENTIFICATION->value),
            TextInput::make('document_number')
                ->label('Número de documento')
                ->dehydrated($enabled)
                ->mask(fn (Get $get) => DocumentTypeEnum::tryFrom((int)$get('document_type'))?->getMask())
                ->maxLength(255),
            TextInput::make('address')
                ->label('Dirección')
                ->dehydrated($enabled)
                ->maxLength(255),
            TextInput::make('email')
                ->dehydrated($enabled)
                ->email()
                ->maxLength(255),
            Fieldset::make('Salario')
                ->dehydrated($enabled)
                ->columnSpan(1)
                ->unless($nested, fn (Fieldset $fieldset) => $fieldset->relationship('salary'))
                ->schema([
                    TextInput::make($salaryFieldNames['amount'])
                        ->mask(RawJs::make('$money($input)'))
                        ->stripCharacters(',')
                        ->numeric()
                        ->required($enabled)
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->columnSpanFull()
                        ->live()
                        ->label('valor'),
                    Toggle::make($salaryFieldNames['modify_distribution'])
                        ->live()
                        ->formatStateUsing(
                            fn (Get $get) =>
                            intval($get($salaryFieldNames['distribution_format'])) !== SalaryDistributionFormatEnum::PERCENTAGE->value ||
                            intval($get($salaryFieldNames['distribution_value'])) !== 50
                        )
                        ->label('Modificar distribución quincenal')
                        ->inline()
                        ->columnSpanFull()
                        ->onColor('warning'),
                    Group::make([
                        Select::make($salaryFieldNames['distribution_format'])
                            ->options(SalaryDistributionFormatEnum::class)
                            ->label('Formato')
                            ->required(fn (Get $get): bool => (bool)$get($salaryFieldNames['modify_distribution']))
                            ->placeholder(null)
                            ->default(SalaryDistributionFormatEnum::PERCENTAGE->value)
                            ->native(false)
                            ->helperText(fn (?string $state) => match (SalaryDistributionFormatEnum::tryFrom(intval($state))) {
                                SalaryDistributionFormatEnum::ABSOLUTE => 'El valor ingresado será restado del total del salario',
                                SalaryDistributionFormatEnum::PERCENTAGE => 'El valor ingresado se calculará al total del salario',
                                default => '',
                            })
                            ->live(),
                        TextInput::make($salaryFieldNames['distribution_value'])
                            ->label('Valor de la primera quincena')
                            ->prefix(fn (Get $get) => match (SalaryDistributionFormatEnum::tryFrom(intval($get($salaryFieldNames['distribution_format'])))) {
                                SalaryDistributionFormatEnum::ABSOLUTE => '0.0',
                                SalaryDistributionFormatEnum::PERCENTAGE => '%',
                                default => '',
                            })
                            ->default(50)
                            ->required(fn (Get $get) => (bool)$get($salaryFieldNames['modify_distribution']))
                            ->maxValue(fn (Get $get) => match (SalaryDistributionFormatEnum::tryFrom(intval($get($salaryFieldNames['distribution_format'])))) {
                                SalaryDistributionFormatEnum::ABSOLUTE => is_float($get($salaryFieldNames['amount']))
                                    ? $get($salaryFieldNames['amount'])
                                    : (float)str_replace(',', '', $get($salaryFieldNames['amount'])),
                                SalaryDistributionFormatEnum::PERCENTAGE => 100,
                                default => PHP_INT_MAX,
                            })
                            ->helperText('El valor restante será usado en la segunda quincena')
                            ->inputMode('decimal')
                            ->numeric()
                            ->minValue(0)
                    ])
                        ->visible(fn (Get $get): bool => (bool)$get($salaryFieldNames['modify_distribution']))
                        ->columnSpanFull()
                ]),
            PhoneRepeater::make('phones')
                ->dehydrated($enabled)
                ->defaultItems(0)
                ->columnSpan(1),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(static::fields(enabled: true));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Empleado'),
                DocumentColumn::make('document_type')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(25)
                    ->tooltip(fn (TextColumn $column) => strlen($state = strval($column->getState())) <= $column->getCharacterLimit() ? null : $state),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }
}
