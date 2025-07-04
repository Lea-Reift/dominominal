<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayrollTypeEnum: int implements HasLabel
{
    case MONTHLY = 1;
    case BIWEEKLY = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensual',
            self::BIWEEKLY => 'Quincenal'
        };
    }
}
