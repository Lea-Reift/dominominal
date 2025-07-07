<?php

declare(strict_types=1);

namespace App\Tables\Columns;

use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Concerns\HasHelperText;
use Filament\Forms\Components\Concerns\HasHint;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Support\RawJs;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Support\ValueObjects\PayrollDisplay\DetailDisplay;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use Filament\Tables\Columns\Concerns\HasLabel;
use Filament\Tables\Columns\Summarizers\Summarizer;

class SalaryAdjustmentColumn extends TextInputColumn
{
    use HasHelperText;
    use HasHint;
    use HasLabel;

    protected string $view = 'components.table.columns.salary-adjustment-column';

    protected SalaryAdjustment $adjustment;
    protected Payroll $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label($this->adjustment->name)
            ->mask(RawJs::make('$money($input)'))
            ->grow(false)
            ->state(function (PayrollDetail $record) {
                $value = $record->salaryAdjustments->keyBy('id')->get($this->adjustment->id)?->detailSalaryAdjustmentValue?->custom_value;

                return is_numeric($value) ? Number::format(floatval($value), 2) : $value;
            })
            ->summarize(
                Summarizer::make()
                    ->using(
                        fn () => (new PayrollDetail())->newEloquentBuilder($this->payroll->details()->toBase())
                            ->asDisplay()
                            ->sum(
                                fn (DetailDisplay $display) => $display
                                    ->{$this->adjustment->type->getKey(plural: true)}
                                    ->get($this->adjustment->parser_alias, 0)
                            )
                    )
                    ->money()
                    ->label("Total {$this->adjustment->name}")
            )
            ->updateStateUsing(function (?string $state, PayrollDetail $record) {
                $relation = tap($record->salaryAdjustments())->detach($this->adjustment->id);

                if (is_null($state)) {
                    return;
                }

                $customValue = floatval(str_replace(',', '', $state));

                $validationFails = match ($this->adjustment->value_type) {
                    SalaryAdjustmentValueTypeEnum::ABSOLUTE => match ($this->adjustment->type) {
                        SalaryAdjustmentTypeEnum::INCOME => $customValue < 0,
                        SalaryAdjustmentTypeEnum::DEDUCTION => $customValue > $record->getParsedPayrollSalary(),
                    },
                    SalaryAdjustmentValueTypeEnum::PERCENTAGE => $customValue < 0 || $customValue > 100,
                    SalaryAdjustmentValueTypeEnum::FORMULA => empty($customValue)
                };

                if ($validationFails) {

                    Notification::make('failed_adjustment_modification')
                        ->title('Valor invalido ')
                        ->body('El valor introducido no es correcto. Intente nuevamente')
                        ->danger()
                        ->color('danger')
                        ->seconds(5)
                        ->send();
                    return;
                }


                $relation->attach($this->adjustment->id, ['custom_value' => $customValue]);
            });
    }

    public static function make(string $name): static
    {
        [, $adjustmentId, $payrollId] = explode('.', $name);

        $static = app(static::class, ['name' => $name]);

        return $static
            ->setAdjustment(SalaryAdjustment::query()->findOrFail($adjustmentId))
            ->setPayroll(Payroll::query()->findOrFail($payrollId))
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
}
