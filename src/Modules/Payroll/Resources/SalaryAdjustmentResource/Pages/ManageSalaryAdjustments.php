<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\SalaryAdjustmentResource\Pages;

use App\Modules\Payroll\Resources\SalaryAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSalaryAdjustments extends ManageRecords
{
    protected static string $resource = SalaryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
