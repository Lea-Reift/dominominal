<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum PayrollTypeEnum: int implements HasLabel, HasDescription
{
    case MONTHLY = 1;
    case BIWEEKLY = 2;

    public function getDescription(): string
    {
        return match ($this) {
            self::MONTHLY => 'Nómina general del mes',
            self::BIWEEKLY => 'Nómina de una de las mitades del mes'
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensual',
            self::BIWEEKLY => 'Quincenal'
        };
    }

    public function isMonthly(): bool
    {
        return $this === self::MONTHLY;
    }

    public function isBiweekly(): bool
    {
        return $this === self::BIWEEKLY;
    }
}
