<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Resources\SalaryAdjustmentResource\Pages;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('parser_alias', str($state)->slug()->upper());
                    })
                    ->maxLength(255),
                Forms\Components\TextInput::make('parser_alias')
                    ->label('Nombre de variable')
                    ->helperText('Este sera el valor utilizado en las formulas de este y los demás ajustes')
                    ->required()
                    ->maxLength(255),
                Select::make('value_type')
                    ->label('Tipo de valor')
                    ->options(SalaryAdjustmentValueTypeEnum::class)
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('value')
                    ->columnSpanFull()
                    ->label('Valor')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre'),
                Tables\Columns\TextColumn::make('parser_alias')
                    ->tooltip('Este sera el valor utilizado en las formulas de este y los demás ajustes')
                    ->label('Nombre de variable'),
                Tables\Columns\TextColumn::make('value_type')
                    ->label('Tipo de valor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor'),
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
            'index' => Pages\ManageSalaryAdjustments::route('/'),
        ];
    }
}
