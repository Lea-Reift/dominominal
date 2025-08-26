<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\CompanyResource\RelationManagers;

use Filament\Schemas\Schema;
use App\Modules\Company\Models\Company;
use App\Modules\Payroll\Resources\Payrolls\PayrollResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * @property Company $ownerRecord
 */
class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    protected static ?string $title = 'Nóminas';

    protected static ?string $modelLabel = 'nómina';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PayrollResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return PayrollResource::table($table);
    }
}
