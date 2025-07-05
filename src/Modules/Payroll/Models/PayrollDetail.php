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

/**
 * @property int $employee_id
 * @property int $payroll_id
 * @property int $salary_id
 * @property-read Employee $employee
 * @property-read Payroll $payroll
 * @property-read Salary $salary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PayrollDetail extends Model
{
    protected $fillable = [
        'employee_id',
        'payroll_id',
        'salary_id',
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
        return $this->belongsToMany(SalaryAdjustment::class);
    }

    public function incomes(): BelongsToMany
    {
        return $this->salaryAdjustments()->where('type', SalaryAdjustmentTypeEnum::INCOME);
    }

    public function deductions(): BelongsToMany
    {
        return $this->salaryAdjustments()->where('type', SalaryAdjustmentTypeEnum::DEDUCTION);
    }
}
