<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Schemas;

use App\Modules\Payroll\Resources\Payrolls\PayrollResource;
use Filament\Schemas\Schema;

class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return PayrollResource::form($schema);
    }
}
