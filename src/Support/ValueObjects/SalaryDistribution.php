<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\SalaryDistributionFormatEnum;

class SalaryDistribution
{
    public function __construct(
        public readonly SalaryDistributionFormatEnum $format,
        public readonly float $value,
    ) {
    }

    public static function make(
        SalaryDistributionFormatEnum $format,
        float $value,
    ): self {
        return new self(
            format: $format,
            value: $value,
        );
    }

    public function toCastArray(): array
    {
        return [
            'distribution_format' => $this->format,
            'distribution_value' => $this->value,
        ];
    }
}
