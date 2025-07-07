<?php

declare(strict_types=1);

namespace App\Tables\Columns;

use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Forms\Components\Concerns\HasHelperText;
use Filament\Forms\Components\Concerns\HasHint;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Support\RawJs;
use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Support\Number;
use Filament\Tables\Columns\Concerns\HasLabel;

class SalaryAdjustmentColumn extends TextInputColumn
{
    use HasHelperText;
    use HasHint;
    use HasLabel;

    protected string $view = 'components.table.columns.salary-adjustment-column';

    protected SalaryAdjustment $adjustment;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label($this->adjustment->name)
            ->mask(RawJs::make('$money($input)'))
            ->grow(false)
            ->state(function (PayrollDetail $record) {
                $value = $record->salaryAdjustments->keyBy('id')->get($this->adjustment->id)?->detailSalaryAdjustmentValue?->custom_value;

                return is_numeric($value) ? Number::format(floatval($value)) : $value;
            })
            ->updateStateUsing(function (?string $state, PayrollDetail $record) {
                $customValue = is_null($state) ? $state : floatval(str_replace(',', '', $state));

                $record->salaryAdjustments()->attach($this->adjustment->id, ['custom_value' => $customValue]);
            });
    }

    public static function make(string $name): static
    {
        [, $adjustmentId] = explode('.', $name);

        $static = app(static::class, ['name' => $name]);

        $static->setAdjustment(SalaryAdjustment::query()->findOrFail($adjustmentId))->configure();
        return $static;
    }

    public function setAdjustment(SalaryAdjustment $salaryAdjustment): self
    {
        $this->adjustment = $salaryAdjustment;
        return $this;
    }
}
