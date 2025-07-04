<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Polyfill\Php80\PhpToken;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TypeError;

class SalaryAdjustmentParser
{
    protected array $cachedVars = [];

    public function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    public function parse(array $vars = [], ?string $formula = null): mixed
    {
        $vars = [...$vars, ...$this->cachedVars];

        if (empty($vars) && empty($formula)) {
            return 0;
        }

        $parser = (new ExpressionLanguage());

        $formula ??= '0';

        if (!empty($vars)) {
            $this->sortVars($vars);
            foreach ($vars as &$var) {
                try {
                    $var = $parser->evaluate($var, $vars);
                } catch (SyntaxError) {
                }
            }
        }

        try {
            return floatval($parser->evaluate($formula, $vars));
        } catch (SyntaxError) {
        } catch (TypeError) {
        } finally {
            return join('; ', $vars). $formula;
        }
    }

    public function parseFromTextVariableInput(string $input, ?string $formula = null): mixed
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

        $vars = array_reduce(
            array: $lines,
            initial: [],
            callback: function (array $carry, array $tokens) {
                $tokens = array_filter($tokens, fn (PhpToken $token) => $token->id !== 61 /* id 61 is singular equal sign (=) */);
                $carry[$tokens[0]->text] = join(' ', array_map(fn (PhpToken $token) => $token->text, array_slice($tokens, 1)));
                return $carry;
            }
        );

        return $this->parse($vars, $formula);
    }

    protected function sortVars(array &$variables): void
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

        uksort($variables, function ($a, $b) use ($priorities) {
            return $priorities[$a] <=> $priorities[$b];
        });
    }
}
