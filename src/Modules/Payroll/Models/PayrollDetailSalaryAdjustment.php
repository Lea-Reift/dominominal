<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $custom_value
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

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->mergeFillable(self::$columns);
    }
}
