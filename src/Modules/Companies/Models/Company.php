<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Concerns\HasDocument;
use App\Concerns\HasPhones;
use App\Enums\DocumentTypeEnum;
use App\Models\User;
use App\Support\ValueObjects\Phone;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property DocumentTypeEnum $document_type
 * @property string $document_number
 * @property string $address
 * @property Collection<int, Phone> $phones
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 */
class Company extends Model
{
    use HasPhones;
    use HasDocument;

    protected $fillable = [
        'id',
        'name',
        'address',
    ];

    public static function boot(): void
    {
        parent::boot();
        static::creating(function (Company $company) {
            $company->user_id ??= Auth::id();
            $company->phones ??= collect();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
