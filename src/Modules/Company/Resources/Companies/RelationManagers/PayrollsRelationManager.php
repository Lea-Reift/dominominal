<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Companies\RelationManagers;

use App\Modules\Company\Resources\Payrolls\PayrollResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    protected static ?string $relatedResource = PayrollResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
