<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\SalaryAdjustmentResource\Pages;

use App\Modules\Payroll\Resources\SalaryAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions\StaticAction;

class ManageSalaryAdjustments extends ManageRecords
{
    protected static string $resource = SalaryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->modalSubmitAction(function (StaticAction $action) {
                $action->extraAttributes(merge: true, attributes: [
                    'wire:loading.attr' => 'disabled'
                ]);
            }),
        ];
    }
}
