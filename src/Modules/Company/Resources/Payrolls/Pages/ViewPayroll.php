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
use Filament\Actions\ReplicateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use App\Enums\SalaryTypeEnum;
use Illuminate\Validation\Rules\Unique;

/**
 * @property-read Payroll $record
 */
class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;
    protected static ?string $navigationLabel = PayrollResource::class;

    protected $listeners = ['updatePayrollData' => '$refresh'];

    protected function getHeaderActions(): array
    {
        return [
            EditPayrollAction::make(),
            ReplicateAction::make()
                ->label('Copiar nómina')
                ->visible($this->record->type->isMonthly())
                ->icon(Heroicon::ClipboardDocument)
                ->schema([
                    Flatpickr::make('period')
                        ->label('Periodo')
                        ->id('month-select')
                        ->format('Y-m-d')
                        ->monthPicker()
                        ->unique(
                            modifyRuleUsing: fn (Unique $rule, string $state) => $rule
                                ->where('company_id', $this->record->company_id)
                                ->where(
                                    'period',
                                    Carbon::parse($state)
                                        ->when(
                                            fn (Carbon $date) => $date->month === 2,
                                            fn (Carbon $date) => $date->day(28),
                                            fn (Carbon $date) => $date->day(30),
                                        )
                                        ->toDateString()
                                )
                                ->where('type', SalaryTypeEnum::MONTHLY)
                        )
                        ->default(now())
                        ->displayFormat('F-Y')
                        ->closeOnDateSelection()
                        ->required(),
                ])
                ->modalHeading("Replicar nómina de {$this->getHeading()}")
                ->modalWidth(Width::Medium)
                ->databaseTransaction()
                ->beforeReplicaSaved(fn (Payroll $replica) => $replica->unsetRelations())
                ->after(fn (Payroll $replica) => $this->record->cloneInto($replica))
                ->successRedirectUrl(fn (Payroll $replica) => ViewPayroll::getUrl([
                    'company' => $replica->company_id,
                    'record' => $replica->id,
                ]))
            ,
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
