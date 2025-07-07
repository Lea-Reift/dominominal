<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * AsValueObjectCollection
 *
 * @implements CastsAttributes<Collection<int, object>, string>
 */
class AsValueObjectCollection implements CastsAttributes
{
    public function __construct(
        protected string $valueObjectClass,
    ) {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return collect(is_string($value) ? json_decode($value, true) : $value)
            ->map(fn (array $values) => $this->valueObjectClass::make(...$values));
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return mixed
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof Collection) {
            return $value->toJson();
        }

        return json_encode($value);
    }
}
