<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Traits;

use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use InvalidArgumentException;
use App\Modules\Payroll\Models\SalaryAdjustment;

/**
 * @mixin Payroll
 */
trait IsAClonablePayroll
{
    public function cloneInto(Payroll $clonedPayroll, bool $checkIfPayrollIsBiweekly = true): Payroll
    {
        throw_if(
            $checkIfPayrollIsBiweekly && $this->monthly_payroll_id !== null,
            InvalidArgumentException::class,
            'No se puede replicar una nómina quincenal'
        );

        $clonedPayroll->salaryAdjustments()->attach($this->salaryAdjustments->modelKeys());

        $this->details
            ->each(function (PayrollDetail $detail) use ($clonedPayroll) {
                $clonedDetail = $detail
                    ->replicate()
                    ->unsetRelations()
                    ->fill(['payroll_id' => $clonedPayroll->id]);

                $clonedDetail->save();

                $adjustments = $detail->salaryAdjustments
                    ->mapWithKeys(fn (SalaryAdjustment $adjustment) => [
                        $adjustment->id => ['custom_value' => $adjustment->detailSalaryAdjustmentValue?->custom_value]
                    ]);

                $clonedDetail->salaryAdjustments()->sync($adjustments);
            });

        if ($this->biweeklyPayrolls->isNotEmpty()) {
            $this->biweeklyPayrolls->each(function (Payroll $biweeklyPayroll) use ($clonedPayroll) {
                $clonedBiweeklyPayroll = $biweeklyPayroll
                    ->replicate()
                    ->unsetRelations()
                    ->fill(['monthly_payroll_id' => $clonedPayroll->id]);

                $clonedBiweeklyPayroll->save();

                $biweeklyPayroll->cloneInto($clonedBiweeklyPayroll, checkIfPayrollIsBiweekly: false);
            });
        }

        return $clonedPayroll->fresh([
            'salaryAdjustments',
            'details',
            'biweeklyPayrolls',
        ]);
    }
}
