<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources\SalaryAdjustments\Pages;

use Filament\Actions\Action;
use App\Modules\Payroll\Resources\SalaryAdjustments\SalaryAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalaryAdjustments extends ManageRecords
{
    protected static string $resource = SalaryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalSubmitAction(function (Action $action) {
                    $action->extraAttributes(merge: true, attributes: [
                        'wire:loading.attr' => 'disabled'
                    ]);
                }),
        ];
    }
}
