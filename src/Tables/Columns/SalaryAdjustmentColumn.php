<?php

declare(strict_types=1);

namespace App\Tables\Columns;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Concerns\HasHint;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Support\RawJs;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\PayrollDetailSalaryAdjustment;
use App\Support\ValueObjects\PayrollDisplay\PayrollDetailDisplay;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection as IlluminateCollection;

class SalaryAdjustmentColumn extends TextInputColumn
{
    use HasHint;

    protected string $view = 'components.table.columns.salary-adjustment-column';

    protected SalaryAdjustment $adjustment;
    protected Payroll $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        $disabled = $this->disableInput(...);

        $this
            ->label($this->adjustment->name)
            ->hint($this->adjustment->type->getLabel())
            ->mask(RawJs::make('$money($input)'))
            ->grow(false)
            ->disabled($disabled)
            ->tooltip(fn (PayrollDetail $record) => $disabled($record) ? 'Este ajuste no se puede modificar porque ya se ha modificado desde una nómina quincenal' : null)
            ->state(function (PayrollDetail $record) {
                $value = $record->salaryAdjustments->keyBy('id')->get($this->adjustment->id)?->detailSalaryAdjustmentValue?->custom_value;

                return is_numeric($value) ? Number::format(floatval($value), 2) : $value;
            })
            ->summarize(
                Summarizer::make()
                    ->using(
                        fn (Builder $query) => (new PayrollDetail())->newEloquentBuilder($query)
                            ->asDisplay()
                            ->sum(fn (PayrollDetailDisplay $display) => $display->salaryAdjustments->get($this->adjustment->parser_alias, 0))
                    )
                    ->money()
                    ->label("Total {$this->adjustment->name}")
            )
            ->updateStateUsing($this->updateDetailAdjustmet(...));
    }

    public static function make(string $name): static
    {
        [, $adjustmentId, $payrollId] = explode('.', $name);

        $static = app(static::class, ['name' => $name]);

        return $static
            ->setAdjustment(SalaryAdjustment::query()->findOrFail($adjustmentId))
            ->setPayroll(Payroll::query()->with(['biweeklyPayrolls' => ['details' => ['salaryAdjustments']]])->findOrFail($payrollId))
            ->configure();
    }

    public function setAdjustment(SalaryAdjustment $salaryAdjustment): self
    {
        $this->adjustment = $salaryAdjustment;
        return $this;
    }

    public function setPayroll(Payroll $payroll): self
    {
        $this->payroll = $payroll;
        return $this;
    }

    public function updateDetailAdjustmet(?string $state, PayrollDetail $record): void
    {
        if (!is_null($state)) {
            $state = floatval(str_replace(',', '', $state));

            $validationFails = match ($this->adjustment->value_type) {
                SalaryAdjustmentValueTypeEnum::ABSOLUTE => match ($this->adjustment->type) {
                    SalaryAdjustmentTypeEnum::INCOME => $state < 0,
                    SalaryAdjustmentTypeEnum::DEDUCTION => $state > $record->getParsedPayrollSalary(),
                },
                SalaryAdjustmentValueTypeEnum::PERCENTAGE => $state < 0 || $state > 100,
                SalaryAdjustmentValueTypeEnum::FORMULA => empty($state)
            };

            if ($validationFails) {

                Notification::make('failed_adjustment_modification')
                    ->title('Valor invalido')
                    ->body('El valor introducido no es correcto. Intente nuevamente')
                    ->danger()
                    ->color('danger')
                    ->seconds(5)
                    ->send();
                return;
            }
        }

        DB::transaction(function () use ($record, $state) {
            $record->salaryAdjustments()->syncWithoutDetaching([$this->adjustment->id => ['custom_value' => $state]]);

            if ($record->payroll->biweeklyPayrolls()->exists()) {
                $this->updateBiweeklyPayrolls($record, $state);
                return;
            }

            $this->updateMonthlyPayroll($record);
        });
    }

    protected function updateBiweeklyPayrolls(PayrollDetail $record, mixed $state): void
    {
        PayrollDetailSalaryAdjustment::query()
            ->where('salary_adjustment_id', $this->adjustment->id)
            ->whereIn(
                'payroll_detail_id',
                PayrollDetail::query()
                    ->select(['id'])
                    ->where('employee_id', $record->employee_id)
                    ->whereHas(
                        'payroll.monthlyPayroll',
                        fn (EloquentBuilder $query) => $query->where('id', $record->payroll_id)
                    )
            )
            ->update([
                'custom_value' => $state / 2
            ]);
    }

    protected function updateMonthlyPayroll(PayrollDetail $record): void
    {
        $monthlyPayrollId = $record->payroll->monthly_payroll_id;
        if (!$monthlyPayrollId) {
            return;
        };

        $biweeklyPayrollDetailsQuery = PayrollDetail::query()
            ->select('id')
            ->where('employee_id', $record->employee_id)
            ->whereHas(
                'payroll.monthlyPayroll',
                fn (EloquentBuilder $query) => $query->where('id', $monthlyPayrollId)
            );

        $biweeklyPayrollsTotalValue = PayrollDetailSalaryAdjustment::query()
            ->where('salary_adjustment_id', $this->adjustment->id)
            ->whereIn(
                'payroll_detail_id',
                $biweeklyPayrollDetailsQuery
            )
            ->numericAggregate('sum', ['custom_value']);


        $monthlyEmployeePayrollDetailQuery = PayrollDetail::query()
            ->select('id')
            ->where('employee_id', $record->employee_id)
            ->where('payroll_id', $monthlyPayrollId)
            ->limit(1);

        PayrollDetailSalaryAdjustment::query()
            ->where('salary_adjustment_id', $this->adjustment->id)
            ->where('payroll_detail_id', $monthlyEmployeePayrollDetailQuery)
            ->update([
                'custom_value' => $biweeklyPayrollsTotalValue,
            ]);
    }

    protected function disableInput(PayrollDetail $record): bool
    {
        if ($this->payroll->type->isBiweekly()) {
            return false;
        }

        /** @var ?SalaryAdjustment */
        $recordAdjustment = $record->salaryAdjustments->find($this->adjustment);

        if (!$recordAdjustment) {
            return false;
        }

        $recordAdjustmentCustomValue = $recordAdjustment->detailSalaryAdjustmentValue?->custom_value;

        if (is_null($recordAdjustmentCustomValue)) {
            return false;
        }

        $biweeklyPayrolls = $this->payroll->biweeklyPayrolls;

        if ($biweeklyPayrolls->isEmpty()) {
            return false;
        }

        $biweeklyPayrollDetails = $biweeklyPayrolls
            ->map(fn (Payroll $payroll) => $payroll->details->firstWhere('employee_id', $record->employee_id))
            ->filter();

        if ($biweeklyPayrollDetails->isEmpty()) {
            return false;
        }

        $biweeklyPayrollDetailAdjustments = $biweeklyPayrollDetails
            ->map(fn (PayrollDetail $detail) => $detail->salaryAdjustments->firstWhere('id', $this->adjustment->id))
            ->filter();

        if ($biweeklyPayrollDetailAdjustments->isEmpty()) {
            return false;
        }

        /** @var IlluminateCollection<int, mixed> $biweeklyPayrollDetailAdjustmentsCustomValues */
        $biweeklyPayrollDetailAdjustmentsCustomValues = $biweeklyPayrollDetailAdjustments
            ->map(fn (SalaryAdjustment $adjustment) => $adjustment->detailSalaryAdjustmentValue?->custom_value)
            ->toBase();

        if ($biweeklyPayrollDetailAdjustmentsCustomValues->isEmpty()) {
            return false;
        }

        return $biweeklyPayrollDetailAdjustmentsCustomValues
            ->some(fn (mixed $value) => !is_null($value) && (float)$value !== ((float)$recordAdjustmentCustomValue / 2));
    }

    public static function fromIterable(iterable $adjustments, Payroll $payroll): array
    {
        $columns = [];

        foreach ($adjustments as $adjustmentId) {
            $columns[] = static::make("salaryAdjustments.{$adjustmentId}.{$payroll->id}");
        }

        return $columns;
    }
}
