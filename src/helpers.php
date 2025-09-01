<?php

declare(strict_types=1);

if (!function_exists('parse_float')) {
    function parse_float(string $value): float
    {
        return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}
