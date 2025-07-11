<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\CompanyResource\Pages;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->successRedirectUrl(fn (Company $record): string => CompanyResource::getUrl('view', ['record' => $record])),
        ];
    }
}
