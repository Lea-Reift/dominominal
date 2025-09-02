<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Pages;

use App\Modules\Company\Resources\Companies\Widgets\PayrollTotalWidget;
use App\Modules\Company\Resources\Payrolls\PayrollResource;
use App\Modules\Payroll\Actions\HeaderActions\EditPayrollAction;
use App\Modules\Payroll\Exports\PayrollExport;
use App\Modules\Payroll\Models\Payroll;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;
    protected static ?string $navigationLabel = PayrollResource::class;

    protected $listeners = ['updatePayrollData' => '$refresh'];

    protected function getHeaderActions(): array
    {
        return [
            EditPayrollAction::make(),
            ActionGroup::make([])
                ->hiddenLabel(false)
                ->button()
                ->label('Exportar')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->actions([
                    Action::make('excel_export')
                        ->label('Exportar a Excel')
                        ->action(function (Payroll $record) {
                            $filenameDate = $record->period;

                            $filenameDate = match (true) {
                                $record->type->isMonthly() => $filenameDate->format('m-Y'),
                                default => $filenameDate->toDateString()
                            };

                            return (new PayrollExport($record->display))
                                ->download("Nómina Administrativa {$record->company->name} {$filenameDate}.xlsx", Excel::XLSX);
                        }),
                    Action::make('pdf_export')
                        ->label('Exportar a PDF')
                        ->url(fn (Payroll $record) => route($this->getRouteName(Filament::getPanel()) . '.export.pdf', ['payroll' => $record, 'company' => $record->company_id]))
                        ->openUrlInNewTab(),
                ]),
        ];
    }

    public function getTitle(): string
    {
        /** @var Payroll $record */
        $record = $this->record;
        return "Nómina {$this->getHeading()} de {$record->company->name}";
    }

    public function getRecordTitle(): string
    {
        return $this->getHeading();
    }

    public function getHeading(): string
    {
        /** @var Payroll $record */
        $record = $this->record;
        $format = $record->type->isMonthly()
            ? 'F \d\e\l Y'
            : 'd \d\e F \d\e\l Y';

        return Str::headline($record->period->translatedFormat($format));
    }

    protected function getFooterWidgets(): array
    {
        return [
            PayrollTotalWidget::class,
        ];
    }
}
