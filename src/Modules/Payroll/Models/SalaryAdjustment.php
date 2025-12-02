<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Enums\SalaryAdjustmentTypeEnum;
use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\QueryBuilders\SalaryAdjustmentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property SalaryAdjustmentTypeEnum $type
 * @property string $name
 * @property string $parser_alias
 * @property SalaryAdjustmentValueTypeEnum $value_type
 * @property string $value
 * @property bool $requires_custom_value
 * @property ?PayrollDetailSalaryAdjustment $detailSalaryAdjustmentValue
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static SalaryAdjustmentBuilder query()
 */
class SalaryAdjustment extends Model
{
    protected $fillable = [
        'type',
        'name',
        'parser_alias',
        'value_type',
        'value',
        'requires_custom_value',
        'ignore_in_deductions',
        'is_absolute_adjustment',
        'ignore_in_isr',
    ];

    protected $casts = [
        'type' => SalaryAdjustmentTypeEnum::class,
        'value_type' => SalaryAdjustmentValueTypeEnum::class,
        'requires_custom_value' => 'boolean',
        'ignore_in_deductions' => 'boolean',
        'is_absolute_adjustment' => 'boolean',
    ];

    public function payrolls(): BelongsToMany
    {
        return $this->belongsToMany(Payroll::class);
    }

    public function newEloquentBuilder($query)
    {
        return new SalaryAdjustmentBuilder($query);
    }

    public function payrollDetails(): BelongsToMany
    {
        return $this->belongsToMany(PayrollDetail::class);
    }
}
