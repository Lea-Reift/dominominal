<?php

declare(strict_types=1);

namespace App\Modules\Company\Resources\Companies\Pages;

use App\Modules\Company\Resources\Companies\CompanyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Modules\Company\Models\Company;

/**
 * @property Company $record
 */
class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected static ?string $breadcrumb = 'Información';

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): string
    {
        return static::$breadcrumb;
    }
}
