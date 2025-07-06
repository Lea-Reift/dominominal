<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Polyfill\Php80\PhpToken;

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
        $this->detail->loadMissing('salaryAdjustments', 'payroll.salaryAdjustments');
        $this->parseDefaultVariables();
        $this->parseVariablesFromPayrollDetail($customVariables);
    }

    public function variables(): array
    {
        return $this->variables;
    }

    public function variablesAsCollection(): Collection
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

    protected function parseDefaultVariables(): void
    {
        $this->variables = [
            ...$this->variables,
            ...Arr::map(static::$defaultVariables, fn (mixed $variable) => is_callable($variable) ? $variable($this->detail) : $variable)
        ];
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
        $variables = $this->detail->payroll->salaryAdjustments
            ->map(fn (SalaryAdjustment $adjustment) => $this->detail->salaryAdjustments->firstWhere('id', $adjustment->id) ?? $adjustment)
            ->mapWithKeys(function (SalaryAdjustment $adjustment) {
                $value = $adjustment->requires_custom_value
                    ? $adjustment->detailSalaryAdjustmentValue?->custom_value
                    : $adjustment->value;

                $value = match ($adjustment->value_type) {
                    SalaryAdjustmentValueTypeEnum::PERCENTAGE => (floatval($value) * $this->detail->salary->amount) / 100,
                    default => $value,
                };

                return [$adjustment->parser_alias => $value];
            })
            ->merge($customVariables)
            ->pipe(fn (Collection $adjustments) => $this->sortVariables($adjustments));

        foreach ($variables as $key => $variable) {
            if (is_numeric($variable)) {
                $this->variables[$key] = floatval($variable);
                continue;
            }

            if (!is_string($variable)) {
                continue;
            }

            preg_match_all('/\b[A-Z][A-Z0-9_]*[A-Z0-9]\b/', $variable, $matches);

            if (!empty($matches[0])) {
                $matches = collect($matches[0])
                    ->mapWithKeys(fn (string $match) => [$match => $this->variables[$match] ?? 0]);

                $variable = str_replace($matches->keys()->toArray(), $matches->values()->toArray(), $variable);
            }

            $this->variables[$key] = $this->parse($variable);
        }

        return $this;
    }

    public function parseFromTextVariableInput(string $input, ?string $formula = null): float
    {
        $tokens = PhpToken::tokenize("<?php {$input}");

        if (!isset($tokens[0]) || (count($tokens) === 1 && $tokens[0]->is(T_INLINE_HTML))) {
            return 0;
        }


        $lines = array_reduce($tokens, initial: [], callback: function (array $carry, Phptoken $token) {
            if (!$token->isIgnorable()) {
                $carry[$token->line][] = $token;
            }
            return $carry;
        });

        $this->variables = array_reduce(
            array: $lines,
            initial: [],
            callback: function (array $carry, array $tokens) {
                $tokens = array_filter($tokens, fn (PhpToken $token) => $token->id !== 61 /* id 61 is singular equal sign (=) */);
                $carry[$tokens[0]->text] = join(' ', array_map(fn (PhpToken $token) => $token->text, array_slice($tokens, 1)));
                return $carry;
            }
        );

        return $this->parse($formula);
    }

    protected function sortVariables(Collection $variables): Collection
    {
        $knownVars = [];

        $priorities = [];

        foreach ($variables as $key => $value) {
            switch (true) {
                case is_numeric($value):
                    $priorities[$key] = 1;
                    break;

                case is_string($value):
                    $priority = 2;

                    array_walk($variables, fn ($_, $innerKey) => $innerKey !== $key && str_contains($value, $innerKey) ? $priority++ : $priority);

                    $priorities[$key] = $priority;
                    break;

                default:
                    $priorities[$key] = PHP_INT_MAX;
                    break;
            }
            $knownVars[] = $key;
        }

        return $variables->sortBy(function ($_, $key) use ($priorities) {
            return $priorities[$key];
        });
    }
}
