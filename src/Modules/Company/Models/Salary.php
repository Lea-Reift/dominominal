<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use Illuminate\Support\Carbon;
use App\Enums\SalaryDistributionFormatEnum;
use App\Enums\SalaryTypeEnum;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Support\ValueObjects\SalaryDistribution;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $employee_id
 * @property float $amount
 * @property SalaryDistribution $distribution
 * @property SalaryTypeEnum $type
 * @property-read Employee $employee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Salary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'amount',
        'type',
        'distribution_format',
        'distribution_value',
    ];

    protected $casts = [
        'amount' => 'float',
        'type' => SalaryTypeEnum::class,
        'distribution_format' => SalaryDistributionFormatEnum::class,
        'distribution_value' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function distribution(): Attribute
    {
        return Attribute::make(
            get: fn () => SalaryDistribution::make($this->distribution_format, floatval($this->distribution_value)),
            set: fn (SalaryDistribution $distribution) => $distribution->toCastArray(),
        );
    }

    public function payrollDetails(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
