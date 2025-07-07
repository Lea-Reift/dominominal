<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SalaryAdjustmentValueTypeEnum;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
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
        $this->detail->loadMissing(['salaryAdjustments', 'payroll' => ['salaryAdjustments', 'incomes', 'deductions']]);
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

    protected function p(Collection $variables): Collection
    {
        // $knownVars = [];

        // $priorities = [];

        // foreach ($variables as $key => $value) {
        //     switch (true) {
        //         case is_numeric($value):
        //             $priorities[$key] = 1;
        //             $knownVars[] = $key;
        //             break;

        //         case is_string($value):
        //             // array_walk($variables, fn ($_, $innerKey) => $innerKey !== $key && str_contains($value, $innerKey) ? $priority++ : $priority);
        //             $priority = array_reduce($variables->keys()->toArray(), initial: 2, callback: function (?int $priority, string $innerKey) use ($key, $variables) {
        //                 $value = $variables->get($innerKey);
        //                 if (is_numeric($value)) {
        //                     return $priority;
        //                 }

        //                 if ($innerKey !== $key) {
        //                     dd($innerKey, $key, $value, str_contains((string)$value, $innerKey));
        //                 }

        //                 if ($innerKey !== $key && str_contains((string)$value, $innerKey)) {
        //                     $priority++;
        //                 }

        //                 return $priority;
        //             });

        //             // array_walk($variables, fn ($_, $innerKey) => $innerKey !== $key && str_contains($value, $innerKey) ? $priority++ : $priority);

        //             $priorities[$key] = $priority;
        //             break;

        //         default:
        //             $priorities[$key] = PHP_INT_MAX;
        //             break;
        //     }
        //     $knownVars[] = $key;
        // }

        // return $variables->sortBy(function ($_, $key) use ($priorities) {
        //     return $priorities[$key];
        // })
        // ->map(fn ($value, $key) => ['value' => $value, 'prority' => $priorities[$key]])
        //     ->dd();

        $knownVars = [];

        // Construimos un mapa de prioridad
        $priorities = [];

        $variables = $variables->toArray();

        foreach ($variables as $key => $value) {
            // Claves numéricas (variables sin nombre)
            if (is_int($key)) {
                $priorities[$key] = 0;
                $knownVars[] = is_string($value) ? $value : null;
            }
            // Valores numéricos
            elseif (is_numeric($value)) {
                $priorities[$key] = 1;
                $knownVars[] = $key;
            }
            // Expresiones que dependen de otras
            elseif (is_string($value)) {
                // Contamos cuántas variables conocidas contiene
                $priority = 2;

                foreach ($variables as $innerKey => $_) {
                    if ($innerKey !== $key && strpos($value, $innerKey) !== false) {
                        $priority += 1; // más dependencias, más tarde
                    }
                }

                $priorities[$key] = $priority;
            } else {
                $priorities[$key] = 99;
            }
        }

        // Ordenamos el array de acuerdo a los niveles de prioridad
        uksort($variables, function ($a, $b) use ($priorities) {
            return ($priorities[$a] ?? 99) <=> ($priorities[$b] ?? 99);
        });

        return collect($variables)
            ->dd();
    }

    protected function sortVariables(Collection $variables): Collection
    {
        $variablesNumericas = [];
        $variablesCompuestas = [];
        $variablesOrganizadas = [];

        // Paso 1: Separar variables numéricas de las compuestas
        foreach ($variables as $nombre => $valor) {
            if (is_numeric($valor)) {
                $variablesNumericas[$nombre] = $valor;
            } else {
                $variablesCompuestas[$nombre] = $valor;
            }
        }

        // Paso 2: Añadir las variables numéricas al resultado final primero
        foreach ($variablesNumericas as $nombre => $valor) {
            $variablesOrganizadas[$nombre] = $valor;
        }

        // Paso 3: Procesar las variables compuestas resolviendo dependencias
        // Este bucle continuará hasta que no se puedan resolver más variables
        // o hasta que todas las variables compuestas hayan sido procesadas.
        $cambioRealizado = true;
        while ($cambioRealizado && !empty($variablesCompuestas)) {
            $cambioRealizado = false;
            foreach ($variablesCompuestas as $nombreCompuesta => $expresion) {
                $variablesEnExpresion = [];
                preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', $expresion, $matches); // Extrae nombres de variables
                foreach ($matches[0] as $match) {
                    if ($match !== 'true' && $match !== 'false' && $match !== 'null') { // Evita palabras clave PHP
                        $variablesEnExpresion[] = $match;
                    }
                }

                $todasLasDependenciasResueltas = true;
                foreach ($variablesEnExpresion as $dep) {
                    if (!array_key_exists($dep, $variablesOrganizadas)) {
                        $todasLasDependenciasResueltas = false;
                        break;
                    }
                }

                // Si todas las dependencias de la expresión ya están en $variablesOrganizadas
                if ($todasLasDependenciasResueltas) {
                    $variablesOrganizadas[$nombreCompuesta] = $expresion;
                    unset($variablesCompuestas[$nombreCompuesta]);
                    $cambioRealizado = true; // Se realizó un cambio, intentar otra pasada
                }
            }
        }

        // Paso 4: (Opcional) Añadir las variables compuestas que no se pudieron resolver
        // Esto es útil para depuración o si se desea manejar dependencias circulares/no resueltas
        foreach ($variablesCompuestas as $nombre => $valor) {
            $variablesOrganizadas[$nombre] = $valor;
        }

        return collect($variablesOrganizadas);
    }
}
