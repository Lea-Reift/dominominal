<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\IsEnhanced;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * @method bool isMonthly()
 * @method bool isBiweekly()
 */
enum PayrollTypeEnum: int implements HasLabel, HasDescription
{
    use IsEnhanced;

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
}
