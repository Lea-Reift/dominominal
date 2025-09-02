<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Company\Resources\Payrolls\Pages\ViewPayroll;
use App\Modules\Payroll\Models\Payroll;
use App\Support\Pages\Setup;
use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;

Route::get('/', Setup::class);

Route::prefix('main/companies/{company}/payrolls/{payroll}/details/export')
    ->name(ViewPayroll::getRouteName(Filament::getDefaultPanel()) . '.export.')
    ->group(function () {
        Route::name('pdf')
            ->get('pdf', function (Company $company, Payroll $payroll) {
                return $payroll->display->streamPDF();
            });
    });
