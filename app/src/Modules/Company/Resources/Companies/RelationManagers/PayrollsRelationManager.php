<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Companies\RelationManagers;

use App\Modules\Company\Resources\Payrolls\PayrollResource;
use Filament\Resources\RelationManagers\RelationManager;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    protected static ?string $relatedResource = PayrollResource::class;

    public function isReadOnly(): bool
    {
        return false;
    }
}
