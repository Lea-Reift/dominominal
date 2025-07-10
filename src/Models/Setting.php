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
 * @property string $value
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

    public function value(): Attribute
    {
        return Attribute::make(
            get: fn (string $value, array $attributes) => $attributes['is_encrypted'] ? Crypt::decryptString($value) : $value,
            set: fn (string $value, array $attributes) => $attributes['is_encrypted'] ? Crypt::encryptString($value) : $value,
        );
    }

    public function newEloquentBuilder($query)
    {
        return new SettingQueryBuilder($query);
    }
}
