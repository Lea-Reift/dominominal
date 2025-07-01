<?php

declare(strict_types=1);

namespace App\Modules\Companies\Resources;

use App\Enums\DocumentTypeEnum;
use App\Forms\Components\PhoneRepeater;
use App\Modules\Companies\Resources\EmployeeResource\Pages;
use App\Modules\Companies\Models\Employee;
use App\Tables\Columns\DocumentColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;

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
                    ->relationship(
                        'company',
                        'name',
                        fn (Builder $query) => $query->where('user_id', Auth::id())
                    )
                    ->required(),
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de documento')
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
                    ->maxLength(255),
                PhoneRepeater::make('phones'),
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
