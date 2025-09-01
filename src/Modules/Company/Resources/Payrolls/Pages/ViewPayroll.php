<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Payrolls\Pages;

use App\Modules\Company\Resources\Companies\Widgets\PayrollTotalWidget;
use App\Modules\Company\Resources\Payrolls\PayrollResource;
use App\Modules\Payroll\Actions\HeaderActions\EditPayrollAction;
use App\Modules\Payroll\Exports\PayrollExport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
                        ->action(function () {
                            $payroll = $this->record;
                            $filenameDate = $payroll->period;

                            $filenameDate = match (true) {
                                $payroll->type->isMonthly() => $filenameDate->format('m-Y'),
                                default => $filenameDate->toDateString()
                            };

                            return (new PayrollExport($payroll->display))
                                ->download("Nómina Administrativa {$payroll->company->name} {$filenameDate}.xlsx", Excel::XLSX);
                        }),
                    Action::make('pdf_export')
                        ->label('Exportar a PDF')
                        ->url(fn () => $this->getUrl(['record' => $this->record->id, 'company' => $this->record->company_id]) . '/export/pdf')
                        ->openUrlInNewTab(),
                ]),
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

    protected function getFooterWidgets(): array
    {
        return [
            PayrollTotalWidget::class,
        ];
    }
}
