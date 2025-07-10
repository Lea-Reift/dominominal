<?php

declare(strict_types=1);

namespace App\QueryBuilders;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @mixin EloquentBuilder
 */
class SettingQueryBuilder extends EloquentBuilder implements BuilderContract
{
    public function getSettings(string $setting): Collection
    {
        return $this->where('setting', $setting)->get();
    }
}
