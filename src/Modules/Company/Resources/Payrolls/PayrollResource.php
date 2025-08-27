<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Modules\Company\Resources\Payrolls\Schemas\PayrollForm;
use App\Modules\Company\Resources\Payrolls\Tables\PayrollsTable;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Company\Resources\Companies\CompanyResource;
use App\Modules\Company\Resources\Payrolls\Pages\ViewPayroll;
use Filament\Resources\ParentResourceRegistration;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = CompanyResource::class;

    protected static ?string $recordTitleAttribute = 'period';

    public static function form(Schema $schema): Schema
    {
        return PayrollForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollsTable::configure($table)
            ->recordUrl(fn (Payroll $record) => ViewPayroll::getUrl(['record' => $record, 'company' => $record->company_id]));
    }

    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return CompanyResource::asParent()
            ->relationship('payrolls')
            ->inverseRelationship('company');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewPayroll::route('/{record}/details'),
        ];
    }
}
