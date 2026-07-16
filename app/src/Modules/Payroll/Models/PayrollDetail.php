<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Company\Models\Salary;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Modules\Payroll\QueryBuilders\PayrollDetailBuilder;
use App\Support\ValueObjects\PayrollDisplay\PayrollDetailDisplay;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use App\Enums\SalaryDistributionFormatEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilderContract;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $payroll_id
 * @property int $salary_id
 * @property-read PayrollDetailDisplay $display
 * @property-read Employee $employee
 * @property-read Payroll $payroll
 * @property-read ?PayrollDetail $complementaryDetail
 * @property-read ?PayrollDetail $monthlyDetail
 * @property-read Salary $salary
 * @property-read Collection<int, SalaryAdjustment> $salaryAdjustments
 * @property-read Collection<int, SalaryAdjustment> $editableSalaryAdjustments
 * @property-read Collection<int, PayrollDetailSalaryAdjustment> $editableSalaryAdjustmentValues
 * @property Collection<int, SalaryAdjustment> $incomes
 * @property Collection<int, SalaryAdjustment> $deductions
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

    protected static function boot(): void
    {
        parent::boot();
        static::deleting(function (PayrollDetail $payrollDetail) {
            $payrollDetail->salaryAdjustments()->detach();
        });
    }

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

    public function editableSalaryAdjustmentValues(): HasMany
    {
        return $this->hasMany(PayrollDetailSalaryAdjustment::class)
            ->whereRelation('salaryAdjustment', 'requires_custom_value', true)
            ->chaperone('payrollDetail');
    }

    public function editableSalaryAdjustments(): BelongsToMany
    {
        return $this->salaryAdjustments()->where('requires_custom_value', true);
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
        return Attribute::get(fn () => new PayrollDetailDisplay($this))->shouldCache();
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

        return $this->payroll->period->day <= 15
            ? $firstBiweekSalary
            : $secondBiweekSalary;
    }

    public function complementaryDetail(): HasOne
    {
        $relation = $this->hasOne(self::class, 'employee_id', 'employee_id');

        if (!$this->payroll) {
            return $relation->whereRaw('1=2');
        }

        $complementaryPayrollDate = $this->payroll->period->clone();
        $complementaryPayrollDate->setDay($complementaryPayrollDate->day !== 14 ? 14 : 28);

        return $relation
            ->whereHas(
                'payroll',
                fn (EloquentBuilderContract $query) => $query
                    ->where('monthly_payroll_id', $this->payroll->monthly_payroll_id)
                    ->whereDate('period', $complementaryPayrollDate)
            );
    }

    public function monthlyDetail(): HasOne
    {
        return $this->hasOne(self::class, 'employee_id', 'employee_id')
            ->when(
                $this->payroll,
                fn (EloquentBuilderContract $query) => $query
                    ->whereHas(
                        'payroll',
                        fn (EloquentBuilderContract $query) => $query
                            ->where('id', $this->payroll->monthly_payroll_id)
                    ),
                fn (EloquentBuilderContract $query) => $query->whereRaw('1=2')
            );
    }

    /**
     * @return HasMany<self>
     */
    public function biweeklyDetails(): HasMany
    {
        return $this->hasMany(self::class, 'employee_id', 'employee_id')
            ->whereHas(
                'payroll',
                fn (EloquentBuilderContract $query) => $query
                    ->where('monthly_payroll_id', $this->payroll->id)
            );
    }

}
