<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Enums\PayrollTypeEnum;
use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property PayrollTypeEnum $type
 * @property Carbon $period
 * @property-read Company $company
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payroll extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'period',
    ];

    protected $casts = [
        'type' => PayrollTypeEnum::class,
        'period' => 'date:Y-m-d',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
