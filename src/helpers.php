<?php

declare(strict_types=1);

if (!function_exists('parse_float')) {
    function parse_float(string $value): float
    {
        return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}

if (!function_exists('system_version')) {
    function system_version(bool $fresh = false): string
    {
        static $systemVersion;

        if (!$systemVersion || $fresh) {
            $systemVersion = json_decode(file_get_contents(base_path('dominominal.version.json')))->version ?? '0.1.0';
        }

        return $systemVersion;
    }
}
