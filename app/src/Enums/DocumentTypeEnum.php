<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\IsEnhanced;
use Filament\Support\Contracts\HasLabel;

enum DocumentTypeEnum: int implements HasLabel
{
    use IsEnhanced;

    case RNC = 1;
    case IDENTIFICATION = 2;
    case PASSPORT = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::RNC => 'Registro Nacional de Contribuyentes (RNC)',
            self::IDENTIFICATION => 'Cédula de Identidad y Electoral',
            self::PASSPORT => 'Pasaporte'
        };
    }

    public function getAcronym(): string
    {
        return match ($this) {
            self::RNC => 'RNC',
            self::IDENTIFICATION => 'Cédula',
            self::PASSPORT => 'Pasaporte',
        };
    }

    public function getMask(): string
    {
        return match ($this) {
            self::IDENTIFICATION => '999-9999999-9',
            self::RNC => '999999999',
            self::PASSPORT => '**********',
        };
    }
}
