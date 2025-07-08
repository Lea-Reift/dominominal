<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\CompanyResource\RelationManagers;

use App\Concerns\HasEmployeeForm;
use App\Tables\Columns\DocumentColumn;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesRelationManager extends RelationManager
{
    use HasEmployeeForm;
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
            ->schema($this->fields(enabled: true));
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
