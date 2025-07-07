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
    protected string $cacheKey = 'salary_adjustment_parser.variables';

    protected array $variables = [];

    protected static array $defaultVariables = [];

    public function __construct(
        protected PayrollDetail $detail,
        array $customVariables = [],
    ) {
        $this->cacheKey .= ".{$detail->id}";
        $this->detail->loadMissing(['salaryAdjustments', 'payroll' => ['salaryAdjustments', 'incomes', 'deductions']]);
        $this->parseVariablesFromPayrollDetail($customVariables);
    }

    public function variables(): Collection
    {
        return collect($this->variables);
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
        $variables = $this->variables;

        if (empty($variables) && empty($formula)) {
            return 0;
        }

        $parser = new ExpressionLanguage();

        return floatval($parser->evaluate($formula, $variables));
    }

    public function parseVariablesFromPayrollDetail(array $customVariables): self
    {
        $defaultVariables = Arr::map(static::$defaultVariables, fn (mixed $variable) => is_callable($variable) ? $variable($this->detail) : $variable);
        $this->detail->payroll->salaryAdjustments

            // Map adjustments into variables
            ->map(fn (SalaryAdjustment $adjustment) => $this->detail->salaryAdjustments->firstWhere('id', $adjustment->id) ?? $adjustment)
            ->mapWithKeys(function (SalaryAdjustment $adjustment) {
                $value = $adjustment->requires_custom_value
                    ? $adjustment->detailSalaryAdjustmentValue?->custom_value
                    : $adjustment->value;

                $value = match ($adjustment->value_type) {
                    SalaryAdjustmentValueTypeEnum::PERCENTAGE => (floatval($value) * $this->detail->salary->amount) / 100,
                    default => $value,
                };

                return [$adjustment->parser_alias => is_null($value) ? 0 : $value];
            })

            // Add custom and default variables
            ->union($defaultVariables)
            ->union($customVariables)

            // Sort variable in parsing order
            ->pipe(fn (Collection $adjustments) => $this->sortVariables($adjustments))

            // parse variables
            ->each(fn (string |float $variable, string $key) => $this->variables[$key] =  is_string($variable) ? $this->parse($variable) : $variable);

        return $this;
    }

    protected function sortVariables(Collection $variables): Collection
    {
        [$parsedVariables, $compositeVariables] = $variables
            ->groupBy(fn (mixed $value) => is_numeric($value) ? 0 : 1, true);

        $changed = true;
        while ($changed && $compositeVariables->isNotEmpty()) {
            $changed = false;
            $compositeVariables->each(function (string $expression, string $key) use ($parsedVariables, $compositeVariables, &$changed) {
                $variablesInExpresion = collect(PhpToken::tokenize("<?php {$expression}"))
                    ->filter(fn (PhpToken $token) => $token->is([T_STRING, T_VARIABLE]))
                    ->map(fn (PhpToken $token) => $token->is(T_VARIABLE) ? str($token->text)->substr(1)->snake()->upper()->toString() : $token->text);

                $allDependenciesFixed = true;
                $variablesInExpresion->each(function (string $variable) use (&$allDependenciesFixed, $parsedVariables) {
                    $allDependenciesFixed = $parsedVariables->has($variable);
                    return $allDependenciesFixed;
                });

                if ($allDependenciesFixed) {
                    $parsedVariables->put($key, $expression);
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

        return $parsedVariables;
    }
}
