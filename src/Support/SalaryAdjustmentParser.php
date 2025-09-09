<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Illuminate\Support\Collection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Polyfill\Php80\PhpToken;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class SalaryAdjustmentParser
{
    protected array $variables = [];

    protected static array $defaultVariables = [];

    public function __construct(
        protected PayrollDetail $detail,
        array $customVariables = [],
    ) {
        $parsedDeductions = $this->parseDeductions()->keyBy('id');
        $this->detail->salaryAdjustments
            ->transform(
                fn (SalaryAdjustment $salaryAdjustment) => $parsedDeductions->has($salaryAdjustment->id)
                    ? $parsedDeductions->get($salaryAdjustment->id)
                    : $salaryAdjustment
            );

        $this->parseVariablesFromPayrollDetail($customVariables);
    }

    public function variables(): Collection
    {
        return collect($this->variables)->except('DETALLE');
    }

    public static function make(PayrollDetail $detail, array $customVariables = []): self
    {
        return new self(detail: $detail, customVariables: $customVariables);
    }

    public static function setDefaultVariables(array $defaultVariables): array
    {
        return static::$defaultVariables = array_merge(static::$defaultVariables, $defaultVariables);
    }

    protected function parse(string $formula): float
    {
        $parser = new ExpressionLanguage();

        return floatval($parser->evaluate($formula, $this->variables));
    }

    public function parseVariablesFromPayrollDetail(array $customVariables): self
    {
        $defaultVariables = Arr::map(static::$defaultVariables, fn (mixed $variable) => is_callable($variable) ? $variable($this->detail) : $variable);

        $this->detail->salaryAdjustments
            // Map adjustments into variables
            ->mapWithKeys(function (SalaryAdjustment $adjustment) {
                $value = $adjustment->requires_custom_value
                    ? $adjustment->detailSalaryAdjustmentValue?->custom_value
                    : $adjustment->value;

                $value = match ($adjustment->value_type) {
                    SalaryAdjustmentValueTypeEnum::PERCENTAGE => (floatval($value) * $this->detail->salary->amount) / 100,
                    default => $value,
                };

                return [$adjustment->parser_alias => is_numeric($value) ? floatval($value) : ($value ?? 0)];
            })
            ->toBase()

            // Add custom and default variables
            ->merge($defaultVariables)
            ->merge($customVariables)
            // Sort variable in parsing order
            ->pipe(fn (Collection $adjustments) => $this->sortVariables($adjustments))

            // Add detail into scope
            ->prepend($this->detail, 'DETALLE')
            // parse variables
            ->each(
                fn (mixed $variable, string $key) =>
                $this->variables[$key] = match (true) {
                    is_string($variable) => $this->parse($variable),
                    is_array($variable) => Arr::map($variable, fn ($formula) => $this->parse($formula)),
                    default => $variable,
                }
            );

        return $this;
    }

    protected function sortVariables(Collection $variables): Collection
    {
        $parsedVariables = collect();
        $compositeVariables = collect();
        $finalVariables = collect();

        $variables
            ->each(fn (mixed $value, string $key) => match (true) {
                is_numeric($value) || (is_string($value) && str_contains($value, 'DETALLE')) => $parsedVariables->put($key, $value),
                is_array($value) => $finalVariables->put($key, $value),
                default => $compositeVariables->put($key, $value)
            });

        $changed = true;
        while ($changed && $compositeVariables->isNotEmpty()) {
            $changed = false;
            $compositeVariables
                ->each(function (string $expression, string $key) use ($parsedVariables, $compositeVariables, $finalVariables, &$changed) {
                    $variablesInExpresion = collect(PhpToken::tokenize("<?php {$expression}"))
                        ->filter(fn (PhpToken $token) => $token->is(T_STRING))
                        ->map(fn (PhpToken $token) => $token->text);

                    $allDependenciesFixed = true;
                    $variablesInExpresion->each(function (string $variable) use (&$allDependenciesFixed, $parsedVariables, $finalVariables) {
                        return $allDependenciesFixed = $parsedVariables->has($variable) || $finalVariables->has($variable);
                    });

                    if ($allDependenciesFixed) {
                        if ($variablesInExpresion->contains(fn (string $variable) => $finalVariables->has($variable))) {
                            $finalVariables->put($key, $expression);
                        } else {
                            $parsedVariables->put($key, $expression);
                        }
                        $compositeVariables->forget($key);
                        $changed = true;
                    }
                });
        }

        if ($compositeVariables->isNotEmpty()) {
            $missingVariables = $compositeVariables->map(fn (string $formula, string $key) => "{$key} = {$formula}");

            throw new InvalidArgumentException(
                'Las siguientes variables no pudieron despejarse porque sus fórmulas contienen valores inexistentes: ' . $missingVariables->join('; ')
            );
        }

        return $parsedVariables->union($finalVariables);
    }

    protected function parseDeductions(): Collection
    {
        $fixedDeductions = $this->detail->deductions;

        $totalIncomesWithFullSalaryFormula = '(' . $this->detail->incomes->pluck('parser_alias')->push('SALARIO')->join(' + ') . ')';

        $fixedDeductions
            ->map(function (SalaryAdjustment $deduction) use ($totalIncomesWithFullSalaryFormula) {
                $stringableValue = str($deduction->value);

                $modifyAdjustment =
                    !$deduction->requires_custom_value &&
                    $deduction->value_type->isFormula() &&
                    $this->detail->complementaryDetail?->deductions->contains(fn ($d) => $d->id === $deduction->id) &&
                    $stringableValue->contains('TOTAL_INGRESOS');

                if ($modifyAdjustment) {
                    return $deduction;
                }

                $deduction->value = $stringableValue->replace('TOTAL_INGRESOS', $totalIncomesWithFullSalaryFormula)->toString();

                return $deduction;
            });
        return $fixedDeductions;
    }
}
