<?php

declare(strict_types=1);

namespace App\Modules\Companies\Resources\EmployeeResource\Pages;

use App\Modules\Companies\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
