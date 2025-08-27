<?php

declare(strict_types=1);

namespace App\Enums;

enum SalaryAdjustmentColumnInputDisablingReasonEnum: int
{
    case NONE = 0;
    case MODIFIED_BY_BIWEEKLY_PAYROLL = 1;
    case NOT_AN_EDITABLE_ADJUSTMENT = 2;

    public function getTooltip(): ?string
    {
        return match ($this) {
            self::MODIFIED_BY_BIWEEKLY_PAYROLL => 'Este ajuste no se puede modificar porque ya se ha modificado desde una nómina quincenal',
            self::NOT_AN_EDITABLE_ADJUSTMENT => 'Este ajuste no se puede modificar porque no es un ajuste modificable',
            default => null,
        };
    }
}
