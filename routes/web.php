<?php

declare(strict_types=1);

use App\Modules\Payroll\Models\Payroll;
use App\Support\Pages\Setup;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Excel;
use App\Modules\Payroll\Exports\PayrollExport;
use App\Modules\Payroll\Models\PayrollDetail;

Route::get('/', Setup::class);

Route::prefix('main/payrolls/{payroll}/details/export')
    ->name('filament.main.payrolls.details.export.')
    ->group(function () {
        Route::name('pdf')
            ->get('pdf', function (Payroll $payroll) {
                return $payroll->display->streamPDF();
            });

        Route::name('excel')
            ->get('excel', function (Payroll $payroll) {
                $filenameDate = $payroll->period;

                $filenameDate = match (true) {
                    $payroll->type->isMonthly() => $filenameDate->format('m-Y'),
                    default => $filenameDate->toDateString()
                };

                return (new PayrollExport($payroll->display))
                    ->download("Nómina Administrativa {$payroll->company->name} {$filenameDate}.xlsx", Excel::XLSX);
            });
    });

Route::prefix('main/payrolls/details/{detail}/export')
    ->name('filament.main.payrolls.details.show.export.')
    ->group(function () {
        Route::name('pdf')
            ->get('pdf', function (PayrollDetail $detail) {
                return $detail->display->streamPDF();
            });
    });
