<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Widgets\SystemVersionWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Exception;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Support\SalaryAdjustmentParser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use DirectoryIterator;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use App\Models\Setting;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

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
                Dashboard::class,
            ])
            ->spa(hasPrefetching: true)
            ->widgets([
                SystemVersionWidget::class,
            ])
            ->topNavigation()
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
            ->maxContentWidth(Width::Full);


        $this->addModulesToPanel($panel);
        return $panel;
    }

    public function boot(): void
    {
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset->columnSpanFull());
        Grid::configureUsing(fn (Grid $grid) => $grid->columnSpanFull());
        Section::configureUsing(fn (Section $section) => $section->columnSpanFull());

        TextEntry::configureUsing(
            fn (TextEntry $textEntry) =>
            $textEntry->formatStateUsing(fn (mixed $state) => is_numeric($state) ? Number::dominicanCurrency($state) : $state)
        );

        Table::configureUsing(
            fn (Table $table) => $table
                ->defaultCurrency('USD')
                ->defaultNumberLocale('en')
                ->paginated(false)
        );

        $this->configureVerifiedEmail();

        if (Schema::hasTable('salary_adjustments')) {
            $this->setSalaryParserDefaultVariables();
        }
    }

    protected function configureVerifiedEmail(): void
    {
        try {

            /** @var EloquentCollection<int, Setting> */
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
        } catch (Exception) {
        }
    }

    public function setSalaryParserDefaultVariables(): void
    {
        $mainAdjustments = SalaryAdjustment::query()->whereIn('parser_alias', ['AFP', 'SFS'])->pluck('value', 'parser_alias');

        SalaryAdjustmentParser::setDefaultVariables([
            'SALARIO' => fn (PayrollDetail $detail) => $detail->salary->amount,
            'SALARIO_QUINCENA' => fn (PayrollDetail $detail) => $detail->getParsedPayrollSalary(),
            'RENGLON_ISR' => fn (PayrollDetail $detail) => $detail->salaryAdjustments->pluck('parser_alias')->contains('ISR')
                ? 'SALARIO_BASE_ISR < 416_220.01 ? 0 : ( SALARIO_BASE_ISR < 624_329.01 ? 1 : ( SALARIO_BASE_ISR < 867_123.01 ? 2 : 3 ))'
                : '0',
            'TOTAL_INGRESOS' => fn (PayrollDetail $detail) => $detail->incomes->pluck('parser_alias')->push('SALARIO_QUINCENA')->join(' + '),
            'TOTAL_DEDUCCIONES' => fn (PayrollDetail $detail) => $detail->deductions->pluck('parser_alias')->push('0')->join(' + '),
            'SALARIO_BASE_DEDUCCIONES' => fn (PayrollDetail $detail) => $detail->salaryAdjustments
                ->where('ignore_in_deductions', false)
                ->pluck('parser_alias')
                ->push('0')
                ->join(' + '),
            'AFP' => $mainAdjustments->get('AFP', 0),
            'SFS' => $mainAdjustments->get('SFS', 0),
            'DEPENDIENTES_ADICIONALES' => fn (PayrollDetail $detail) => $detail->deductions->firstWhere('parser_alias', 'DEPENDIENTES_ADICIONALES')?->detailSalaryAdjustmentValue?->custom_value ?? 0,
            'SALARIO_BASE_ISR' => fn (PayrollDetail $detail) => '((TOTAL_INGRESOS - AFP - SFS - DEPENDIENTES_ADICIONALES) * ' . ($detail->payroll->type->isMonthly() ? 12 : 24) . ')',
            'RENGLONES_ISR' => function (PayrollDetail $detail) {
                if ($detail->salaryAdjustments->pluck('parser_alias')->doesntContain('ISR')) {
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
                        'base' => 867_123.00,
                        'top' => 867_123.00,
                        'rate' => 25,
                        'amount_to_add' => 79_776.00,
                    ],
                ];

                $isrDivisor = $detail->payroll->type->isMonthly() ? 12 : 24;

                return Arr::map(
                    $isrSteps,
                    fn (array $step) =>
                    "(((SALARIO_BASE_ISR - {$step['base']}) * 0.{$step['rate']}) + {$step['amount_to_add']}) / {$isrDivisor}"
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
