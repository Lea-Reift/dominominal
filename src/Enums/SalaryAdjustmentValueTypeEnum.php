<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\IsEnhanced;
use Filament\Support\Contracts\HasLabel;

enum SalaryAdjustmentValueTypeEnum: int implements HasLabel
{
    use IsEnhanced;

    case ABSOLUTE = 1;
    case PERCENTAGE = 2;
    case FORMULA = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::ABSOLUTE => 'Absoluto',
            self::PERCENTAGE => 'Porcentaje',
            self::FORMULA => 'Formula',
        };
    }

}
