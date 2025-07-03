<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Enums\SalaryDistributionFormatEnum;
use App\Support\ValueObjects\SalaryDistribution;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $employee_id
 * @property float $amount
 * @property SalaryDistribution $distribution
 * @property-read Employee $employee
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Salary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'amount',
        'distribution_format',
        'distribution_value',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'distribution_format' => SalaryDistributionFormatEnum::class,
        'distribution_value' => 'decimal:2',
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
}
