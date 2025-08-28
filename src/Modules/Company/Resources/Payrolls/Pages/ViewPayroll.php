<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Pages;

use App\Modules\Company\Resources\Payrolls\PayrollResource;
use App\Modules\Payroll\Actions\HeaderActions\EditPayrollAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;
    protected static ?string $navigationLabel = PayrollResource::class;


    protected function getHeaderActions(): array
    {
        return [
            EditPayrollAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return "Nómina {$this->getHeading()} de {$this->record->company->name}";
    }

    public function getRecordTitle(): string
    {
        return $this->getHeading();
    }

    public function getHeading(): string
    {
        $format = $this->record->type->isMonthly()
            ? 'F \d\e\l Y'
            : 'd \d\e F \d\e\l Y';

        return Str::headline($this->record->period->translatedFormat($format));
    }
}
