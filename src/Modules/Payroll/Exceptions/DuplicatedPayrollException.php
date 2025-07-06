<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Exceptions;

use Illuminate\Support\Carbon;
use Exception;

class DuplicatedPayrollException extends Exception
{
    public function __construct(Carbon $date)
    {
        parent::__construct("Ya existe la nómina para el día {$date->translatedFormat('d/m/Y')}");
    }

    public static function make(Carbon $date): self
    {
        return new self($date);
    }
}
