<?php

declare(strict_types=1);

namespace App\Modules\Payroll\QueryBuilders;

use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @mixin EloquentBuilder
 */
class PayrollDetailBuilder extends EloquentBuilder implements BuilderContract
{
    public function __construct(Builder $query)
    {
        parent::__construct($query);
        $this->model = new PayrollDetail();
    }

    public function asDisplay(): Collection
    {
        return $this->get()->toBase()->pluck('display');
    }
}
