<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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

    protected static ?string $parentResource = CompanyResource::class;

    protected static ?string $recordTitleAttribute = 'period';
    protected static ?string $modelLabel = 'nómina';

    protected $listeners = ['updatePayrollData' => '$refresh'];

    public static function form(Schema $schema): Schema
    {
        return PayrollForm::configure($schema)
            ->disabled(false);
    }

    public static function table(Table $table): Table
    {
        return PayrollsTable::configure($table);
    }

    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return CompanyResource::asParent();
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewPayroll::route('/{record}/details'),
        ];
    }
}
