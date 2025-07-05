<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

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
}
