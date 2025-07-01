<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Concerns\HasDocument;
use App\Concerns\HasPhones;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\DocumentTypeEnum;
use App\Support\ValueObjects\Phone;

/**
 *
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $surname
 * @property DocumentTypeEnum $document_type
 * @property string $document_number
 * @property string $address
 * @property string $email
 * @property Collection<int, Phone> $phones
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Company|null $user
 */
class Employee extends Model
{
    use HasPhones;
    use HasDocument;

    protected $fillable = [
        'company_id',
        'name',
        'surname',
        'address',
        'email',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fullName(): Attribute
    {
        return Attribute::get(
            fn () => "{$this->getAttribute('name')} {$this->getAttribute('surname')}"
        );
    }
}
