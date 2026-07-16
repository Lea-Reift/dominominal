<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class Phone implements Arrayable
{
    public function __construct(
        public string $type,
        public string $number,
    ) {
    }

    public function toArray(): array
    {
        return (array) $this;
    }

    public static function make(
        string $type,
        string $number,
    ): self {
        return new self(
            type: $type,
            number: $number
        );
    }
}
