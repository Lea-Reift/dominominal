<?php

declare(strict_types=1);

namespace App\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use App\Concerns\HasDocument;

class DocumentColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->formatStateUsing(fn (HasDocument $record) => "{$record->document_type->getAcronym()}: {$record->document_number}")
            ->label('Documento');
    }
}
