<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');

function actingAsUser(): void
{
    test()->actingAs(\App\Models\User::factory()->create());
}

function createCompanyWithEmployees(int $employeeCount = 3): array
{
    $company = \App\Modules\Company\Models\Company::factory()->create();
    $employees = \App\Modules\Company\Models\Employee::factory()
        ->count($employeeCount)
        ->create(['company_id' => $company->id]);

    return compact('company', 'employees');
}

function createPayrollWithDetails(int $detailCount = 2): array
{
    $company = \App\Modules\Company\Models\Company::factory()->create();
    $employees = \App\Modules\Company\Models\Employee::factory()
        ->count($detailCount)
        ->create(['company_id' => $company->id]);

    $payroll = \App\Modules\Payroll\Models\Payroll::factory()
        ->create(['company_id' => $company->id]);

    $payrollDetails = collect();
    foreach ($employees as $employee) {
        $payrollDetails->push(
            \App\Modules\Payroll\Models\PayrollDetail::factory()->create([
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
            ])
        );
    }

    return compact('company', 'employees', 'payroll', 'payrollDetails');
}
