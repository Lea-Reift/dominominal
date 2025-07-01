<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\DocumentTypeEnum;

/**
 * @property array $fillable
 * @property array $casts
 */
trait HasDocument
{
    public function initializeHasDocument(): void
    {
        $this->fillable = $this->fillable + [
            'document_type',
            'document_number',
        ];

        $this->casts['document_type'] = DocumentTypeEnum::class;
    }
}
