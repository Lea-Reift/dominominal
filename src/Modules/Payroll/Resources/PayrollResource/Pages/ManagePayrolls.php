<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\PayrollResource\Pages;

use App\Modules\Payroll\Resources\PayrollResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePayrolls extends ManageRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
