<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum SalaryAdjustmentTypeEnum: int implements HasLabel
{
    case INCOME = 1;
    case DEDUCTION = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::INCOME => 'Ingreso',
            self::DEDUCTION => 'Descuento',
        };
    }

    public function getKey(): string
    {
        return Str::slug($this->name, '_');
    }
}
