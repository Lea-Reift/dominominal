<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\HeaderActions;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use App\Modules\Payroll\Resources\Payrolls\PayrollResource;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;

class EditPayrollAction
{
    protected EditAction $action;

    public function __construct()
    {
        $this->action =
            EditAction::make()
                ->schema(fn (Schema $schema) => PayrollResource::form($schema))
                ->databaseTransaction()
                ->after(function (Payroll $editedPayroll) {
                    if ($editedPayroll->type->isBiweekly()) {
                        $this->updateMonthlyPayroll($editedPayroll);
                        return;
                    }

                    $currentMontlyPayrollSalaryAdjustments = $editedPayroll->salaryAdjustments()->pluck('salary_adjustments.id')->toArray();

                    Payroll::query()
                        ->whereMonth('period', $editedPayroll->period->month)
                        ->whereYear('period', $editedPayroll->period->year)
                        ->where('id', '!=', $editedPayroll->id)
                        ->update([
                            'monthly_payroll_id' => $editedPayroll->id,
                        ]);

                    $editedPayroll->details()->get()->each( # @phpstan-ignore-next-line
                        fn (PayrollDetail $detail) => $this->updateDetailSalaryAdjustmentsForEntity($detail, $currentMontlyPayrollSalaryAdjustments)
                    );

                    $this->updateDetailSalaryAdjustmentsForEntity($editedPayroll, $currentMontlyPayrollSalaryAdjustments);

                    $biweeklyPayrolls = $editedPayroll->biweeklyPayrolls()->with('details')->get();

                    if ($biweeklyPayrolls->isEmpty()) {
                        return;
                    }

                    // @phpstan-ignore-next-line
                    $biweeklyPayrolls->each(function (Payroll $biweeklyPayroll) use ($currentMontlyPayrollSalaryAdjustments) {
                        $this->updateDetailSalaryAdjustmentsForEntity($biweeklyPayroll, $currentMontlyPayrollSalaryAdjustments);
                        $biweeklyPayroll->details->each(
                            // @phpstan-ignore-next-line
                            fn (PayrollDetail $detail) => $this->updateDetailSalaryAdjustmentsForEntity($detail, $currentMontlyPayrollSalaryAdjustments)
                        );
                    });
                })
                ->slideOver();
    }

    public function getAction(): EditAction
    {
        return $this->action;
    }

    public static function make(): EditAction
    {
        return (new self())->getAction();
    }

    public function updateDetailSalaryAdjustmentsForEntity(Payroll|PayrollDetail $entity, array $salaryAdjustmentsModification): void
    {
        $relation = $entity->salaryAdjustments();

        $currentPayrollSalaryAdjustments = $relation->pluck($relation->getRelatedPivotKeyName())->toArray();

        $salaryAdjustmentsToAdd = array_diff($salaryAdjustmentsModification, $currentPayrollSalaryAdjustments);
        $salaryAdjustmentsToRemove = array_diff($currentPayrollSalaryAdjustments, $salaryAdjustmentsModification);

        $relation->syncWithoutDetaching($salaryAdjustmentsToAdd);
        $relation->detach($salaryAdjustmentsToRemove);
    }

    protected function updateMonthlyPayroll(Payroll $editedPayroll): void
    {
        $monthlyPayrollId = Payroll::query()
            ->whereDate('period', $editedPayroll->period->setDay(match (true) {
                $editedPayroll->period->month === 2 => 28,
                default => 30,
            }))
            ->value('id');

        $editedPayroll->update(['monthly_payroll_id' => $monthlyPayrollId]);
    }
}
