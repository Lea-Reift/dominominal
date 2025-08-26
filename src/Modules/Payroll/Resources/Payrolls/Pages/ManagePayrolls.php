<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\Payrolls\Pages;

use Filament\Actions\CreateAction;
use App\Modules\Payroll\Resources\Payrolls\PayrollResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePayrolls extends ManageRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
