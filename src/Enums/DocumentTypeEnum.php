<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentTypeEnum: int implements HasLabel
{
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
        return match($this) {
            self::RNC => 'RNC',
            self::IDENTIFICATION => 'Cédula',
            self::PASSPORT => 'Pasaporte',
        };
    }
}
