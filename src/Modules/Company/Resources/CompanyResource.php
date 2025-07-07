<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources;

use App\Modules\Company\Resources\CompanyResource\Pages;
use App\Modules\Company\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Pages\SubNavigationPosition;
use App\Enums\DocumentTypeEnum;
use App\Forms\Components\PhoneRepeater;
use App\Modules\Company\Resources\CompanyResource\RelationManagers\EmployeesRelationManager;
use App\Modules\Payroll\Resources\CompanyResource\RelationManagers\PayrollsRelationManager;
use Filament\Forms\Get;
use Filament\Tables\Actions\EditAction;
use App\Modules\Payroll\Resources\PayrollResource\Pages\ManageCompanyPayrollDetails;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'compañía';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de documento')
                    ->options(DocumentTypeEnum::class)
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('document_number')
                    ->label('Número de documento')
                    ->required()
                    ->mask(fn (Get $get) => DocumentTypeEnum::tryFrom(intval($get('document_type')))?->getMask())
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),
                PhoneRepeater::make('phones')
                    ->grid([
                        'sm' => 2,
                        'md' => 3,
                        'xl' => 4,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            'employees' => EmployeesRelationManager::class,
            'payrolls' => PayrollsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'view' => Pages\ViewCompany::route('/{record}'),
            'payroll_details' => ManageCompanyPayrollDetails::route('/{company}/payrolls/{record}/details')
        ];
    }
}
