<?php

declare(strict_types=1);

use App\Modules\Payroll\Exceptions\DuplicatedPayrollException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('DuplicatedPayrollException - Basic Functionality', function () {
    test('can create exception instance with date', function () {
        $date = Carbon::parse('2024-01-15');
        $exception = new DuplicatedPayrollException($date);

        expect($exception)->toBeInstanceOf(DuplicatedPayrollException::class);
        expect($exception)->toBeInstanceOf(\Exception::class);
    });

    test('exception message contains formatted date', function () {
        $date = Carbon::parse('2024-01-15');
        $exception = new DuplicatedPayrollException($date);

        expect($exception->getMessage())->toContain('15/01/2024');
        expect($exception->getMessage())->toContain('Ya existe la nómina para el día');
    });

    test('can create exception using static make method', function () {
        $date = Carbon::parse('2024-02-28');
        $exception = DuplicatedPayrollException::make($date);

        expect($exception)->toBeInstanceOf(DuplicatedPayrollException::class);
        expect($exception->getMessage())->toContain('28/02/2024');
    });

    test('handles different date formats correctly', function () {
        $date = Carbon::parse('2024-12-31');
        $exception = new DuplicatedPayrollException($date);

        expect($exception->getMessage())->toContain('31/12/2024');
    });
});

describe('DuplicatedPayrollException - Usage Scenarios', function () {
    test('exception provides meaningful error context for different months', function () {
        $january = Carbon::parse('2024-01-01');
        $february = Carbon::parse('2024-02-15');

        $janException = new DuplicatedPayrollException($january);
        $febException = new DuplicatedPayrollException($february);

        expect($janException->getMessage())->toContain('01/01/2024');
        expect($febException->getMessage())->toContain('15/02/2024');
    });

    test('exception can be caught and handled', function () {
        $caught = false;
        $date = Carbon::parse('2024-03-15');

        try {
            throw new DuplicatedPayrollException($date);
        } catch (DuplicatedPayrollException $e) {
            $caught = true;
            expect($e->getMessage())->toContain('15/03/2024');
        }

        expect($caught)->toBeTrue();
    });

    test('exception message is consistent', function () {
        $date = Carbon::parse('2024-04-20');
        $exception = new DuplicatedPayrollException($date);

        $originalMessage = $exception->getMessage();

        expect($originalMessage)->toContain('20/04/2024');
        expect($exception->getCode())->toBe(0);
    });
});

describe('DuplicatedPayrollException - Edge Cases', function () {
    test('handles leap year dates', function () {
        $leapDate = Carbon::parse('2024-02-29');
        $exception = new DuplicatedPayrollException($leapDate);

        expect($exception->getMessage())->toContain('29/02/2024');
    });

    test('handles year boundaries', function () {
        $newYear = Carbon::parse('2024-01-01');
        $yearEnd = Carbon::parse('2024-12-31');

        $newYearException = new DuplicatedPayrollException($newYear);
        $yearEndException = new DuplicatedPayrollException($yearEnd);

        expect($newYearException->getMessage())->toContain('01/01/2024');
        expect($yearEndException->getMessage())->toContain('31/12/2024');
    });

    test('handles dates with different years', function () {
        $date2023 = Carbon::parse('2023-06-15');
        $date2025 = Carbon::parse('2025-06-15');

        $exception2023 = new DuplicatedPayrollException($date2023);
        $exception2025 = new DuplicatedPayrollException($date2025);

        expect($exception2023->getMessage())->toContain('15/06/2023');
        expect($exception2025->getMessage())->toContain('15/06/2025');
    });

    test('handles timezone aware dates', function () {
        $utcDate = Carbon::parse('2024-07-15 12:00:00', 'UTC');
        $localDate = Carbon::parse('2024-07-15 12:00:00', 'America/New_York');

        $utcException = new DuplicatedPayrollException($utcDate);
        $localException = new DuplicatedPayrollException($localDate);

        // Both should format the date part the same way
        expect($utcException->getMessage())->toContain('15/07/2024');
        expect($localException->getMessage())->toContain('15/07/2024');
    });
});

describe('DuplicatedPayrollException - Integration with Laravel', function () {
    test('exception can be logged', function () {
        $date = Carbon::parse('2024-08-10');
        $exception = new DuplicatedPayrollException($date);

        expect(fn () => logger()->error($exception->getMessage()))->not->toThrow(\Throwable::class);
    });

    test('exception can be used in validation context', function () {
        $date = Carbon::parse('2024-09-05');
        $exception = new DuplicatedPayrollException($date);

        expect($exception)->toBeInstanceOf(\Throwable::class);
    });

    test('exception maintains stack trace', function () {
        $date = Carbon::parse('2024-10-25');
        $exception = new DuplicatedPayrollException($date);

        expect($exception->getTrace())->toBeArray();
        expect($exception->getTraceAsString())->toBeString();
    });

    test('static make method works correctly', function () {
        $date = Carbon::parse('2024-11-11');
        $exception = DuplicatedPayrollException::make($date);

        expect($exception)->toBeInstanceOf(DuplicatedPayrollException::class);
        expect($exception->getMessage())->toContain('11/11/2024');
    });
});

describe('DuplicatedPayrollException - Error Scenarios', function () {
    test('handles current date correctly', function () {
        $now = Carbon::now();
        $exception = new DuplicatedPayrollException($now);

        expect($exception->getMessage())->toContain($now->format('d/m/Y'));
    });

    test('handles past dates correctly', function () {
        $pastDate = Carbon::parse('2020-01-01');
        $exception = new DuplicatedPayrollException($pastDate);

        expect($exception->getMessage())->toContain('01/01/2020');
    });

    test('handles future dates correctly', function () {
        $futureDate = Carbon::parse('2030-12-31');
        $exception = new DuplicatedPayrollException($futureDate);

        expect($exception->getMessage())->toContain('31/12/2030');
    });
});
