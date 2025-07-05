<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
        'total_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
