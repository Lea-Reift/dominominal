<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\CompanyResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Enums\DocumentTypeEnum;
use App\Forms\Components\PhoneRepeater;
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

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';
    protected static ?string $title = 'Empleados';
    protected static ?string $modelLabel = 'empleado';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombres')
                    ->required()
                    ->maxLength(255),
                TextInput::make('surname')
                    ->label('Apellidos')
                    ->required()
                    ->maxLength(255),
                Select::make('document_type')
                    ->label('Tipo de documento')
                    ->native(false)
                    ->options(DocumentTypeEnum::class)
                    ->live()
                    ->default(DocumentTypeEnum::IDENTIFICATION->value),
                TextInput::make('document_number')
                    ->label('Número de documento')
                    ->mask(fn (Get $get) => DocumentTypeEnum::tryFrom((int)$get('document_type'))?->getMask())
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                PhoneRepeater::make('phones')
                    ->columnSpan(1),
                Fieldset::make('Salario')
                    ->columnSpan(1)
                    ->relationship('salary')
                    ->schema([
                        TextInput::make('amount')
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->numeric()
                            ->required()
                            ->inputMode('decimal')
                            ->minValue(0)
                            ->columnSpanFull()
                            ->live()
                            ->label('valor'),
                        Toggle::make('modify_distribution')
                            ->live()
                            ->label('Modificar distribución quincenal')
                            ->inline()
                            ->columnSpanFull()
                            ->onColor('warning'),
                        Group::make([
                            Select::make('distribution_format')
                                ->options(SalaryDistributionFormatEnum::class)
                                ->label('Formato')
                                ->required(fn (Get $get): bool => (bool)$get('modify_distribution'))
                                ->placeholder(null)
                                ->default(SalaryDistributionFormatEnum::PERCENTAGE->value)
                                ->native(false)
                                ->helperText(fn (?string $state) => match (SalaryDistributionFormatEnum::tryFrom(intval($state))) {
                                    SalaryDistributionFormatEnum::ABSOLUTE => 'El valor ingresado será restado del total del salario',
                                    SalaryDistributionFormatEnum::PERCENTAGE => 'El valor ingresado se calculará al total del salario',
                                    default => '',
                                })
                                ->live(),
                            TextInput::make('distribution_value')
                                ->label('Valor de la primera quincena')
                                ->prefix(fn (Get $get) => match (SalaryDistributionFormatEnum::tryFrom(intval($get('distribution_format')))) {
                                    SalaryDistributionFormatEnum::ABSOLUTE => '0.0',
                                    SalaryDistributionFormatEnum::PERCENTAGE => '%',
                                    default => '',
                                })
                                ->default(50)
                                ->required(fn (Get $get) => (bool)$get('modify_distribution'))
                                ->maxValue(fn (Get $get) => match (SalaryDistributionFormatEnum::tryFrom(intval($get('distribution_format')))) {
                                    SalaryDistributionFormatEnum::ABSOLUTE => (float)str_replace(',', '', $get('amount')),
                                    SalaryDistributionFormatEnum::PERCENTAGE => 100,
                                    default => PHP_INT_MAX,
                                })
                                ->helperText('El valor restante será usado en la segunda quincena')
                                ->inputMode('decimal')
                                ->numeric()
                                ->minValue(0)
                        ])
                            ->visible(fn (Get $get): bool => (bool)$get('modify_distribution'))
                            ->columnSpanFull()
                    ])
            ]);
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
                    ->tooltip(fn (TextColumn $column) => strlen($state = $column->getState()) <= $column->getCharacterLimit() ? null : $state),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }
}
