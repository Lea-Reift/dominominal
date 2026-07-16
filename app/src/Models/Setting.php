<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\QueryBuilders\SettingQueryBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

/**
 * @property string $setting
 * @property string $name
 * @property mixed $value
 * @property bool $is_encrypted
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @method static SettingQueryBuilder query()
 */
class Setting extends Model
{
    protected $fillable = [
        'setting',
        'name',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean'
    ];

    protected $attributes = [
        'is_encrypted' => false,
    ];

    public function __construct(array $attributes = [])
    {
        $attributes = $this->attributes + $attributes;
        parent::__construct($attributes);
    }

    public function value(): Attribute
    {
        return Attribute::make(
            get: function (string $value, array $attributes) {
                $value = json_validate($value) ? json_decode($value) : $value;
                return ($attributes['is_encrypted'] ?? false) ? Crypt::decryptString($value) : $value;
            },
            set: fn (mixed $value, array $attributes) => json_encode($attributes['is_encrypted'] ? Crypt::encryptString($value) : $value),
        );
    }

    public function newEloquentBuilder($query)
    {
        return new SettingQueryBuilder($query);
    }
}
