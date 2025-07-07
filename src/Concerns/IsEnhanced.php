<?php

declare(strict_types=1);

namespace App\Concerns;

use BadMethodCallException;
use Illuminate\Support\Stringable;

trait IsEnhanced
{
    abstract public static function cases(): array;

    public function __call($name, $arguments)
    {
        if (!str_contains($name, 'is')) {
            $className = static::class;
            throw new BadMethodCallException("call to undefined method {$className}::{$name}()");
        }

        $case = str($name)->substr(2)->snake()->upper();
        if ($case->substr(0, 3)->lower()->toString() === 'not') {
            return !$this->isCase($case->substr(4)->toString());
        }
        return $this->isCase($case->toString());
    }

    protected function isCase(string $case): bool
    {
        return $this->name === $case;
    }

    public function getKey(bool $plural = false): string
    {
        return str($this->name)->slug('_')->when($plural, fn (Stringable $str) => $str->plural())->toString();
    }
}
