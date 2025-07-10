<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Enums\PayrollTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Support\ValueObjects\PayrollDisplay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $id
 * @property int $company_id
 * @property PayrollTypeEnum $type
 * @property Carbon $period
 * @property-read Company $company
 * @property-read PayrollDisplay $display
 * @property-read ?Payroll $monthlyPayroll
 * @property-read Collection<int, Payroll> $biweeklyPayrolls
 * @property-read Collection<int, SalaryAdjustment> $salaryAdjustments
 * @property-read Collection<int, SalaryAdjustment> $editableSalaryAdjustments
 * @property-read Collection<int, SalaryAdjustment> $incomes
 * @property-read Collection<int, SalaryAdjustment> $deductions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payroll extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'period',
        'parent_payroll_id'
    ];

    protected $casts = [
        'type' => PayrollTypeEnum::class,
        'period' => 'date:Y-m-d',
    ];

    public static function boot(): void
    {
        parent::boot();
        static::saving(function (Payroll $payroll) {
            if ($payroll->type->isMonthly()) {
                $payroll->period = Carbon::parse($payroll->period)->endOfMonth();
            }
        });

        static::deleting(function (Payroll $payroll) {
            $payroll->details()->delete();
            $payroll->salaryAdjustments()->detach();
        });
    }

    public function monthlyPayroll(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_payroll_id');
    }

    public function biweeklyPayrolls(): HasMany
    {
        return $this->hasMany(static::class, 'parent_payroll_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'payroll_details', 'payroll_id', 'employee_id')
            ->withPivot(['salary_id']);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function salaryAdjustments(): BelongsToMany
    {
        return $this->belongsToMany(SalaryAdjustment::class);
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
        return Attribute::get(fn () => new PayrollDisplay($this));
    }
}
