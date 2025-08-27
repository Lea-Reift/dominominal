<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Tables;

use Filament\Tables\Table;
use App\Modules\Payroll\Resources\Payrolls\PayrollResource;

class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        return PayrollResource::table($table);
    }
}
