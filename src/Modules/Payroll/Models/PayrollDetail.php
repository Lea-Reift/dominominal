<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Company\Models\Salary;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Modules\Payroll\QueryBuilders\PayrollDetailBuilder;
use App\Support\ValueObjects\PayrollDisplay\PayrollDetailDisplay;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use App\Enums\SalaryDistributionFormatEnum;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $payroll_id
 * @property int $salary_id
 * @property-read PayrollDetailDisplay $display
 * @property-read Employee $employee
 * @property-read Payroll $payroll
 * @property-read Salary $salary
 * @property-read Collection<int, SalaryAdjustment> $salaryAdjustments
 * @property-read Collection<int, SalaryAdjustment> $incomes
 * @property-read Collection<int, SalaryAdjustment> $deductions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static PayrollDetailBuilder query()
 */
class PayrollDetail extends Model
{
    protected $fillable = [
        'employee_id',
        'payroll_id',
        'salary_id',
    ];

    protected $with = [
        'payroll'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function company(): HasOneThrough
    {
        return $this->hasOneThrough(Company::class, Payroll::class);
    }

    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }

    public function salaryAdjustments(): BelongsToMany
    {
        return $this->belongsToMany(SalaryAdjustment::class)
            ->as(PayrollDetailSalaryAdjustment::$pivotPropertyName)
            ->withPivot(PayrollDetailSalaryAdjustment::$columns)
            ->using(PayrollDetailSalaryAdjustment::class);
    }

    public function incomes(): BelongsToMany
    {
        return $this->salaryAdjustments()->where('type', SalaryAdjustmentTypeEnum::INCOME);
    }

    public function deductions(): BelongsToMany
    {
        return $this->salaryAdjustments()->where('type', SalaryAdjustmentTypeEnum::DEDUCTION);
    }

    public function display(): Attribute
    {
        return Attribute::get(fn () => new PayrollDetailDisplay($this));
    }

    public function newEloquentBuilder($query): PayrollDetailBuilder
    {
        return new PayrollDetailBuilder($query);
    }

    public function getParsedPayrollSalary(): float
    {
        $salary = $this->salary;
        if ($this->payroll->type->isMonthly()) {
            return $salary->amount;
        }

        $firstBiweekSalary = match ($salary->distribution->format) {
            SalaryDistributionFormatEnum::ABSOLUTE => $salary->distribution->value,
            SalaryDistributionFormatEnum::PERCENTAGE =>  $salary->amount - (($salary->distribution->value * $salary->amount) / 100),
        };

        $secondBiweekSalary = $salary->amount - $firstBiweekSalary;

        return $this->payroll->period->day > 15
            ? $firstBiweekSalary
            : $secondBiweekSalary;
    }
}
