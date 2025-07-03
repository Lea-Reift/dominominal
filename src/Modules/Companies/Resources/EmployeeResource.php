<?php

declare(strict_types=1);

namespace App\Modules\Companies\Resources;

use App\Enums\DocumentTypeEnum;
use App\Enums\SalaryDistributionFormatEnum;
use App\Forms\Components\PhoneRepeater;
use App\Modules\Companies\Resources\EmployeeResource\Pages;
use App\Modules\Companies\Models\Employee;
use App\Tables\Columns\DocumentColumn;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Support\RawJs;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-c-user-group';

    protected static ?string $modelLabel = 'empleado';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombres')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('surname')
                    ->label('Apellidos')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('company_id')
                    ->label('Compañía')
                    ->native(false)
                    ->relationship(
                        'company',
                        'name',
                        fn (Builder $query) => $query->where('user_id', Auth::id())
                    )
                    ->required(),
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de documento')
                    ->native(false)
                    ->options(DocumentTypeEnum::class)
                    ->default(DocumentTypeEnum::IDENTIFICATION),
                Forms\Components\TextInput::make('document_number')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->columnSpanFull()
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
                                ->required(fn (Get $get): bool => (bool)$get('modify_distribution'))
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Compañía')
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Empleado'),
                DocumentColumn::make('document_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEmployees::route('/'),
        ];
    }
}
