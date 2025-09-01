<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Pages;

use App\Modules\Company\Resources\Payrolls\PayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayroll extends EditRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
