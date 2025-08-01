<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Modules\Payroll\Models\PayrollDetail;
use App\Support\SalaryAdjustmentParser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use DirectoryIterator;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use App\Models\Setting;

class MainPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('main')
            ->path('main')
            ->login()
            ->darkMode(false)
            ->profile(isSimple: false)
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'danger' => Color::Red,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])
            ->discoverPages(in: app_path('Support/Pages'), for: 'App\\Support\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->spa()
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->maxContentWidth(MaxWidth::Full);


        $this->addModulesToPanel($panel);
        return $panel;
    }

    public function boot(): void
    {
        Table::$defaultCurrency = 'USD';
        Table::$defaultNumberLocale = 'en';

        $this->setSalaryParserDefaultVariables();
        $this->configureVerifiedEmail();
    }

    protected function configureVerifiedEmail(): void
    {
        try {
            $emailSettings = Setting::query()->getSettings('email');
            $emailSetting = $emailSettings->where('name', 'username')->first();
            $verifiedSetting = $emailSettings->where('name', 'is_verified')->first();

            $hasEmail = $emailSetting && $emailSetting->value;
            $isVerified = $verifiedSetting && $verifiedSetting->value;

            if ($hasEmail && $isVerified) {
                config([
                    'mail.from.address' => $emailSetting->value,
                ]);
            }
        } catch (\Exception) {
        }
    }

    public function setSalaryParserDefaultVariables(): void
    {
        SalaryAdjustmentParser::setDefaultVariables([
            'SALARIO' => 'DETALLE.salary.amount',
            'SALARIO_QUINCENA' => 'DETALLE.getParsedPayrollSalary()',
            'RENGLON_ISR' => fn (PayrollDetail $detail) => $detail->payroll->salaryAdjustments->pluck('parser_alias')->contains('ISR')
                ? 'SALARIO_BASE_ISR < 416_220.01 ? 0 : ( SALARIO_BASE_ISR < 624_329.01 ? 1 : ( SALARIO_BASE_ISR < 867_123.01 ? 2 : 3 ))'
                : '0',
            'TOTAL_INGRESOS' => fn (PayrollDetail $detail) => $detail->incomes->pluck('parser_alias')->push('SALARIO_QUINCENA')->join(' + '),
            'TOTAL_DEDUCCIONES' => fn (PayrollDetail $detail) => ($deductions = $detail->deductions)->isNotEmpty()
                ? $deductions->pluck('parser_alias')->join(' + ')
                : '0',
            'HORAS_EXTRA' => fn (PayrollDetail $detail) => $detail->payroll->salaryAdjustments->pluck('parser_alias')->contains('HORAS_EXTRA')
                ? $detail->payroll->salaryAdjustments->keyBy('name')->get('HORAS_EXTRA')?->value
                : '0',
            'SALARIO_BASE_ISR' =>
            fn (PayrollDetail $detail) => $detail->payroll->salaryAdjustments->pluck('parser_alias')->contains(['ISR', 'AFP', 'SFS'])
                ? '((TOTAL_INGRESOS - AFP - SFS) * ' . ($detail->payroll->type->isMonthly() ? 12 : 24) . ')'
                : '0',
            'RENGLONES_ISR' => function (PayrollDetail $detail) {
                if ($detail->payroll->salaryAdjustments->pluck('parser_alias')->doesntContain('ISR')) {
                    return array_fill(0, 4, '0');
                }

                $isrSteps = [
                    [
                        'base' => 0,
                        'top' => 416_220,
                        'rate' => 0,
                        'amount_to_add' => 0,
                    ],
                    [
                        'base' => 416_220.01,
                        'top' => 624_329.00,
                        'rate' => 15,
                        'amount_to_add' => 0,
                    ],
                    [
                        'base' => 624_329.01,
                        'top' => 867_123.00,
                        'rate' => 20,
                        'amount_to_add' => 31_216.00,
                    ],
                    [
                        'base' => 0,
                        'top' => 416_220,
                        'rate' => 0,
                        'amount_to_add' => 0,
                    ],
                ];

                return Arr::map(
                    $isrSteps,
                    fn (array $step) =>
                    "(((SALARIO_BASE_ISR - {$step['base']}) * 0.{$step['rate']}) + {$step['amount_to_add']}) /" . ($detail->payroll->type->isMonthly() ? 12 : 24)
                );
            }
        ]);
    }

    public function addModulesToPanel(Panel $panel): void
    {
        $modulesFolder = new DirectoryIterator(app_path('Modules'));

        foreach ($modulesFolder as $directory) {
            /** @var DirectoryIterator $directory */

            if ($directory->isDot() || $directory->isFile()) {
                continue;
            }

            $directoryResourcesPath = $directory->getRealPath() . DIRECTORY_SEPARATOR . 'Resources';
            $directoryPagesPath = $directory->getRealPath() . DIRECTORY_SEPARATOR . 'Pages';

            $baseNamespace = str($directory->getPath())
                ->append('\\')
                ->replace([app_path(), DIRECTORY_SEPARATOR], ['App', '\\']);

            $resourcesNamespace = $baseNamespace
                ->append("{$directory->getBasename()}\\Resources")
                ->toString();

            $pagesNamespace = $baseNamespace
                ->append("{$directory->getBasename()}\\Pages")
                ->toString();

            $panel->discoverResources($directoryResourcesPath, $resourcesNamespace);
            $panel->discoverPages($directoryPagesPath, $pagesNamespace);
        }
    }

}
