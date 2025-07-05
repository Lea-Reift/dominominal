<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Enums\PayrollTypeEnum;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'payroll_details', 'payroll_id', 'employee_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
