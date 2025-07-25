<?php

declare(strict_types=1);

use App\Modules\Payroll\Models\Payroll;
use App\Support\Pages\Setup;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::get('/', Setup::class);

$panel = Filament::getPanel('main');

if ($panel && ($resource = $panel->getModelResource(Payroll::class))) {
    $panelId = $panel->getId();

    $resourceId = $resource::getRoutePrefix();
    Route::get("{$panelId}/{$resourceId}/{payroll}/details/pdf", function (Payroll $payroll) {
        return $payroll->display->streamPDF();
    });
}
