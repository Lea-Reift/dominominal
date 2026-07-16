<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

readonly class Command
{
    public string $path;

    public function __construct(
        public string $command,
        ?string $path = null
    ) {
        $this->path = $path ?? base_path();
    }
}
