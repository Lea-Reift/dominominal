<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Casts\AsValueObjectCollection;
use App\Support\ValueObjects\Phone;

/**
 * @property array $fillable
 * @property array $casts
 */
trait HasPhones
{
    public function initializeHasPhones(): void
    {
        $this->fillable[] = 'phones';
        $this->casts['phones'] = AsValueObjectCollection::class . ':' . Phone::class;
    }
}
