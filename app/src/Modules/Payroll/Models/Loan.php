<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Support\Carbon;
use App\Modules\Company\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property float $total_amount
 * @property float $installment_amount
 * @property int $instalments_total
 * @property int $instalments_paid
 * @property-read Employee $employee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Loan extends Model
{
    protected $fillable = [
        'employee_id',
        'total_amount',
        'installment_amount',
        'instalments_total',
        'instalments_paid',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'installment_amount' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
