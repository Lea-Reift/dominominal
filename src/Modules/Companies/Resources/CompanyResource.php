<?php

declare(strict_types=1);

namespace App\Modules\Companies\Resources;

use App\Enums\DocumentTypeEnum;
use App\Forms\Components\PhoneRepeater;
use App\Modules\Companies\Resources\CompanyResource\Pages;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Resources\CompanyResource\Pages\ManageCompanyEmployees;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Pages\Page;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'compañía';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de documento')
                    ->options(DocumentTypeEnum::class)
                    ->required(),
                Forms\Components\TextInput::make('document_number')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),
                PhoneRepeater::make('phones'),
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
            ->filters([
                //
            ])
            ->actions([
                Action::make('employees')
                    ->label('Empleados')
                    ->icon('heroicon-c-user-group')
                    ->url(fn (Company $record) => ManageCompanyEmployees::getUrl(['record' => $record])),
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
            'index' => Pages\ManageCompanies::route('/'),
            'employees' => Pages\ManageCompanyEmployees::route('{record}/employees')
        ];
    }

    // public static function getRecordSubNavigation(Page $page): array
    // {
    //     return $page->generateNavigationItems([
    //         Pages\ManageCompanyEmployees::class,
    //     ]);
    // }
}
