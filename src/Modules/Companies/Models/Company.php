<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Casts\AsValueObjectCollection;
use App\Enums\DocumentTypeEnum;
use App\Models\User;
use App\Support\ValueObjects\Phone;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    protected $fillable = [
        'id',
        'name',
        'document_type',
        'document_number',
        'address',
        'phones',
    ];

    protected $casts = [
        'phones' => AsValueObjectCollection::class . ":" . Phone::class,
        'document_type' => DocumentTypeEnum::class,
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
