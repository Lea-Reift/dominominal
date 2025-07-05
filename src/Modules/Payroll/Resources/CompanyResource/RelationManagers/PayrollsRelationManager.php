<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\CompanyResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use App\Enums\PayrollTypeEnum;
use App\Modules\Payroll\Models\Payroll;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Get;
use App\Modules\Payroll\Resources\PayrollResource\Pages\ManageCompanyPayrollDetails;
use Filament\Tables\Actions\Action;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';
    protected static ?string $title = 'Nóminas';

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
                    ->options(PayrollTypeEnum::class)
                    ->native(false),

                Flatpickr::make('period')
                    ->label('Periodo')
                    ->id('month-select')
                    ->format('Y-m-d')
                    ->monthPicker()
                    ->visible(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) === PayrollTypeEnum::MONTHLY)
                    ->disabled(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) !== PayrollTypeEnum::MONTHLY)
                    ->displayFormat('F-Y')
                    ->closeOnDateSelection()
                    ->required(),

                Flatpickr::make('period')
                    ->id('date-select')
                    ->label('Periodo')
                    ->format('Y-m-d')
                    ->visible(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) === PayrollTypeEnum::BIWEEKLY)
                    ->disabled(fn (Get $get) => PayrollTypeEnum::tryFrom(intval($get('type'))) !== PayrollTypeEnum::BIWEEKLY)
                    ->displayFormat('d-m-Y')
                    ->closeOnDateSelection()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo'),
                TextColumn::make('period')
                    ->label('Periodo')
                    ->date(fn (Payroll $record) => $record->type->isMonthly() ? 'F-Y' : 'd-m-Y'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modelLabel('nómina'),
            ])
            ->actions([
                Action::make('details')
                    ->url(fn (Payroll $record) => ManageCompanyPayrollDetails::getUrl(['record' => $record])),
                EditAction::make()
                    ->modalHeading('Editar nómina'),
            ]);
    }
}
