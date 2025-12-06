<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Companies;

use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use App\Modules\Company\Resources\Companies\Pages\ListCompanies;
use App\Modules\Company\Resources\Companies\Pages\ViewCompany;
use App\Modules\Company\Models\Company;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use App\Enums\DocumentTypeEnum;
use App\Forms\Components\PhoneRepeater;
use App\Modules\Company\Resources\Companies\RelationManagers\EmployeesRelationManager;
use App\Modules\Company\Resources\Companies\RelationManagers\PayrollsRelationManager;

class CompanyResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Company::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'compañía';
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?\Filament\Pages\Enums\SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type')
                    ->label('Tipo de documento')
                    ->options(DocumentTypeEnum::class)
                    ->live()
                    ->required(),
                TextInput::make('document_number')
                    ->label('Número de documento')
                    ->required()
                    ->mask(fn (Get $get) => $get('document_type')?->getMask())
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),
                PhoneRepeater::make('phones')
                    ->grid([
                        'sm' => 2,
                        'md' => 3,
                        'xl' => 4,
                    ])
                    ->defaultItems(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('document_type')
                    ->label('Documento')
                    ->sortable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
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
            'index' => ListCompanies::route('/'),
            'view' => ViewCompany::route('/{record}'),
        ];
    }
}
