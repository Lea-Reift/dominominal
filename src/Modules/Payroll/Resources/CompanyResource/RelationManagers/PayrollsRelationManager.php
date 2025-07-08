<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\CompanyResource\RelationManagers;

use App\Modules\Company\Models\Company;
use App\Modules\Payroll\Resources\PayrollResource;
use Filament\Forms\Form;
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

    public function form(Form $form): Form
    {
        return PayrollResource::form($form);
    }

    public function table(Table $table): Table
    {
        return PayrollResource::table($table);
    }
}
