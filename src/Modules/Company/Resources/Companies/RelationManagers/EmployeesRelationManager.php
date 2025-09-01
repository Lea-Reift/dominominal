<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Companies\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use App\Concerns\HasEmployeeForm;
use App\Tables\Columns\DocumentColumn;
use Filament\Resources\RelationManagers\RelationManager;
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->fields(enabled: true));
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
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
