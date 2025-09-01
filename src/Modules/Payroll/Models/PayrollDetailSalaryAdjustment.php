<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property float $custom_value
 * @property-read SalaryAdjustment $salaryAdjustment
 * @property-read PayrollDetail $payrollDetail
 */
class PayrollDetailSalaryAdjustment extends Pivot
{
    public $incrementing = true;

    public static string $pivotPropertyName = 'detailSalaryAdjustmentValue';

    public static array $columns = [
        'payroll_detail_id',
        'salary_adjustment_id',
        'custom_value',
    ];

    protected $casts = [
        'custom_value' => 'float',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->mergeFillable(self::$columns);
    }

    public function payrollDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollDetail::class);
    }

    public function salaryAdjustment(): BelongsTo
    {
        return $this->belongsTo(SalaryAdjustment::class);
    }
}
