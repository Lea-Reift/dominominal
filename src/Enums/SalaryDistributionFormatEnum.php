<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SalaryDistributionFormatEnum: int implements HasLabel
{
    case ABSOLUTE = 1;
    case PERCENTAGE = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::ABSOLUTE => 'Absoluto',
            self::PERCENTAGE => 'Porcentaje',
        };
    }
}
