<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\TableActions;

use App\Modules\Payroll\Models\Payroll;
use Filament\Tables\Actions\Action;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Enums\Alignment;
use Filament\Notifications\Notification;
use App\Modules\Payroll\Exceptions\DuplicatedPayrollException;
use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Employee;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Notifications\Actions\Action as NotificationAction;
use stdClass;
use App\Modules\Payroll\Resources\PayrollResource\Pages\PayrollDetailsManager;

class GenerateSecondaryPayrollsAction
{
    protected Action $action;

    public function __construct(
        protected Payroll $record
    ) {
        $disableSecondaryPayrolls =  Payroll::query()
            ->whereIn(
                'period',
                Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->setDay($day)->toDateString()])
            )
            ->where('monthly_payroll_id', $this->record->id)
            ->exists();

        $this->action = Action::make('secondary_payrolls')
            ->label('Generar nóminas secundarias')
            ->modalHeading('Generar nóminas al...')
            ->visible(fn () => $this->record->type->isMonthly())
            ->modalIcon('heroicon-s-clipboard-document')
            ->databaseTransaction()
            ->form($this->formSchema($disableSecondaryPayrolls))
            ->color('success')
            ->modalWidth(MaxWidth::Small)
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalSubmitAction($disableSecondaryPayrolls ? false : null)
            ->action(function (array $data) {
                $payrolls = [];
                foreach ($data['dates'] as $day) {
                    try {
                        $payrolls[] = $this->generateSecondaryPayroll(intval($day));
                    } catch (DuplicatedPayrollException $e) {
                        Notification::make()
                            ->title('Nóminas Secundarias')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();

                        throw new Halt();
                    }
                }
                $actions = Arr::map(
                    $payrolls,
                    fn (Payroll $payroll) => NotificationAction::make("got_to_{$payroll->period->day}_payroll")
                        ->label("Ir a la nómina del {$payroll->period->day}")
                        ->button()
                        ->url(PayrollDetailsManager::getUrl(['record' => $payroll->id]))
                );

                Notification::make()
                    ->title('Nóminas Secundarias')
                    ->success()
                    ->body('Nóminas generadas con éxito')
                    ->actions($actions)
                    ->send();
            });
    }

    public function getAction(): Action
    {
        return $this->action;
    }

    public static function make(Payroll $payroll): Action
    {
        return (new self($payroll))->getAction();
    }

    protected function generateSecondaryPayroll(int $day): Payroll
    {
        $period = $this->record->period->clone()->setDay($day)->startOfDay();

        throw_if(
            condition: Payroll::query()->where('period', $period)->exists(),
            exception: DuplicatedPayrollException::make($period)
        );

        /** @var Payroll $payroll */
        $payroll = tap($this->record->replicate()
            ->unsetRelations()
            ->fill([
                'type' => SalaryTypeEnum::BIWEEKLY,
                'period' => $period,
                'monthly_payroll_id' => $this->record->id,
            ]))
            ->save();

        // SalaryAdjustments
        $payroll->salaryAdjustments()->sync($this->record->salaryAdjustments);

        $details = $this->record->details
            ->when(
                $period->day === 14,
                fn (EloquentCollection $details) => $details->reject(
                    fn (PayrollDetail $detail) => $detail->salary->type->isMonthly()
                )
            );

        // Details
        foreach ($details as $detail) {
            /**
             * @var PayrollDetail $newDetail
             * @var PayrollDetail $detail
             */
            $newDetail =  $detail->replicate()
                ->unsetRelations()
                ->fill([
                    'payroll_id' => $payroll->id,
                ]);

            $newDetail->save();

            $adjustments = $detail->salaryAdjustments
                ->mapWithKeys(fn (SalaryAdjustment $adjustment) => [
                    $adjustment->id => [
                        'custom_value' => $adjustment->detailSalaryAdjustmentValue?->custom_value !== null
                            ? $adjustment->detailSalaryAdjustmentValue->custom_value / 2
                            : null
                    ]
                ]);
            $newDetail->salaryAdjustments()->sync($adjustments);
        }

        return $payroll->fresh();
    }

    protected function formSchema(bool $disableSecondaryPayrolls): array
    {
        $existingSecondaryPayrollsDates = $this->record->biweeklyPayrolls->map(fn (Payroll $secondaryPayroll) => $secondaryPayroll->period->day);

        return [
            CheckboxList::make('dates')
                ->hiddenLabel()
                ->bulkToggleable()
                ->hint(function () use ($disableSecondaryPayrolls) {
                    $hasEmployeesWithMonthlySalary = $this->record->employees
                        ->filter(fn (Employee $employee) => $employee->salary->type->isMonthly())
                        ->isNotEmpty();

                    return match (true) {
                        $disableSecondaryPayrolls => 'Las nóminas del mes ya fueron generadas',
                        $hasEmployeesWithMonthlySalary => 'Los empleados con salarios mensuales no aparecerán en nominas de la primera quincena del mes',
                        default => '',
                    };
                })
                ->hintColor('warning')
                ->validationMessages([
                    'required' => 'Debe seleccionar al menos una fecha'
                ])
                ->required()
                ->gridDirection('row')
                ->default(
                    fn () => DB::query()
                        ->from($this->record->getTable())
                        ->select('period')
                        ->whereIn(
                            'period',
                            Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->setDay($day)->toDateString()])
                        )
                        ->get()
                        ->map(fn (stdClass $secondaryPayroll) => $secondaryPayroll->period->day)
                        ->toArray()
                )
                ->columns(2)
                ->dehydrated(!$disableSecondaryPayrolls)
                ->options(Arr::mapWithKeys([14, 28], fn (int $day) => [$day => $this->record->period->translatedFormat("{$day} \\d\\e F")]))
                ->disableOptionWhen(fn (string $value) => $existingSecondaryPayrollsDates->contains($value)),
        ];
    }
}
