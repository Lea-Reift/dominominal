<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use Illuminate\Support\Carbon;
use App\Concerns\HasDocument;
use App\Concerns\HasPhones;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\DocumentTypeEnum;
use App\Support\ValueObjects\Phone;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Modules\Payroll\Models\Payroll;

/**
 *
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $surname
 * @property ?string $job_title
 * @property DocumentTypeEnum $document_type
 * @property string $document_number
 * @property string $address
 * @property string $email
 * @property-read string $full_name
 * @property Collection<int, Phone> $phones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read ?Salary $salary
 * @property-read EloquentCollection<int, Salary> $salaries
 */
class Employee extends Model
{
    use HasPhones;
    use HasDocument;

    protected $fillable = [
        'company_id',
        'name',
        'surname',
        'job_title',
        'address',
        'email',
    ];

    public static function boot(): void
    {
        parent::boot();
        static::creating(function (Employee $employee) {
            $employee->phones ??= collect();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salary(): HasOne
    {
        return $this->hasOne(Salary::class)->latestOfMany();
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
    }

    public function fullName(): Attribute
    {
        return Attribute::get(
            fn () => "{$this->getAttribute('name')} {$this->getAttribute('surname')}"
        );
    }

    public function payrolls(): BelongsToMany
    {
        return $this->belongsToMany(Payroll::class, 'payroll_details');
    }
}
