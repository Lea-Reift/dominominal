<?php

declare(strict_types=1);

namespace App\Modules\Payroll\QueryBuilders;

use App\Enums\SalaryAdjustmentTypeEnum;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class SalaryAdjustmentBuilder extends EloquentBuilder implements BuilderContract
{
    public function whereType(SalaryAdjustmentTypeEnum $type): self
    {
        return $this->where('type', $type);
    }

    public function incomes(): self
    {
        return $this->whereType(SalaryAdjustmentTypeEnum::INCOME);
    }

    public function deductions(): self
    {
        return $this->whereType(SalaryAdjustmentTypeEnum::DEDUCTION);
    }
}
