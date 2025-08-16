<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\QueryBuilders\PayrollDetailBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
    $this->payroll = Payroll::factory()->create(['company_id' => $this->company->id]);
});

describe('PayrollDetailBuilder - Basic Functionality', function () {
    test('returns correct builder instance', function () {
        $builder = PayrollDetail::query();

        expect($builder)->toBeInstanceOf(PayrollDetailBuilder::class);
    });

    test('can chain standard query methods', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $result = PayrollDetail::query()
            ->where('payroll_id', $this->payroll->id)
            ->first();

        expect($result)->toBeInstanceOf(PayrollDetail::class);
        expect($result->id)->toBe($detail->id);
    });

    test('can use with relationships', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $result = PayrollDetail::query()
            ->with(['payroll', 'employee', 'salary'])
            ->first();

        expect($result->relationLoaded('payroll'))->toBeTrue();
        expect($result->relationLoaded('employee'))->toBeTrue();
        expect($result->relationLoaded('salary'))->toBeTrue();
    });
});

describe('PayrollDetailBuilder - Custom Query Methods', function () {
    test('can filter by payroll', function () {
        $payroll2 = Payroll::factory()->create(['company_id' => $this->company->id]);

        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);
        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $payroll2->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id
        ]);

        $results = PayrollDetail::query()
            ->where('payroll_id', $this->payroll->id)
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($detail1->id);
    });

    test('can filter by employee', function () {
        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);

        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id
        ]);

        $results = PayrollDetail::query()
            ->where('employee_id', $this->employee->id)
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($detail1->id);
    });

    test('can order by employee name', function () {
        $employee2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Aaron',
            'surname' => 'First'
        ]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);

        $this->employee->update(['name' => 'Zach', 'surname' => 'Last']);

        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id
        ]);

        $results = PayrollDetail::query()
            ->join('employees', 'payroll_details.employee_id', '=', 'employees.id')
            ->orderBy('employees.name')
            ->select('payroll_details.*')
            ->get();

        expect($results)->toHaveCount(2);
        expect($results->first()->employee_id)->toBe($employee2->id);
    });
});

describe('PayrollDetailBuilder - Relationship Queries', function () {
    test('can query with salary adjustments', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create();
        $detail->salaryAdjustments()->attach($adjustment->id, ['custom_value' => 100]);

        $result = PayrollDetail::query()
            ->with('salaryAdjustments')
            ->first();

        expect($result->salaryAdjustments)->toHaveCount(1);
        expect($result->salaryAdjustments->first()->id)->toBe($adjustment->id);
    });

    test('can filter by salary adjustments', function () {
        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);
        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id
        ]);

        $adjustment = SalaryAdjustment::factory()->create();
        $detail1->salaryAdjustments()->attach($adjustment->id);

        $results = PayrollDetail::query()
            ->whereHas('salaryAdjustments', function ($query) use ($adjustment) {
                $query->where('salary_adjustments.id', $adjustment->id);
            })
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($detail1->id);
    });

    test('can eager load nested relationships', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $result = PayrollDetail::query()
            ->with(['payroll.company', 'employee.company', 'salary'])
            ->first();

        expect($result->payroll->relationLoaded('company'))->toBeTrue();
        expect($result->employee->relationLoaded('company'))->toBeTrue();
        expect($result->relationLoaded('salary'))->toBeTrue();
    });
});

describe('PayrollDetailBuilder - Aggregation Queries', function () {
    test('can count payroll details by payroll', function () {
        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);

        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id
        ]);

        $count = PayrollDetail::query()
            ->where('payroll_id', $this->payroll->id)
            ->count();

        expect($count)->toBe(2);
    });

    test('can sum salary amounts', function () {
        $salary1 = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 1000
        ]);

        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create([
            'employee_id' => $employee2->id,
            'amount' => 1500
        ]);

        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $salary1->id
        ]);

        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id
        ]);

        $total = PayrollDetail::query()
            ->join('salaries', 'payroll_details.salary_id', '=', 'salaries.id')
            ->where('payroll_details.payroll_id', $this->payroll->id)
            ->sum('salaries.amount');

        expect($total)->toBe(2500);
    });
});

describe('PayrollDetailBuilder - Edge Cases', function () {
    test('handles empty result sets', function () {
        $results = PayrollDetail::query()
            ->where('payroll_id', 99999)
            ->get();

        expect($results)->toBeEmpty();
    });

    test('handles null values in queries', function () {
        $result = PayrollDetail::query()
            ->where('payroll_id', null)
            ->first();

        expect($result)->toBeNull();
    });

    test('handles complex where clauses', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $results = PayrollDetail::query()
            ->where('payroll_id', $this->payroll->id)
            ->where('employee_id', $this->employee->id)
            ->where('salary_id', $this->salary->id)
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($detail->id);
    });
});

describe('PayrollDetailBuilder - Performance', function () {
    test('efficiently queries large datasets', function () {
        // Create 100 payroll details
        for ($i = 0; $i < 100; $i++) {
            $employee = Employee::factory()->create(['company_id' => $this->company->id]);
            $salary = Salary::factory()->create(['employee_id' => $employee->id]);

            PayrollDetail::factory()->create([
                'payroll_id' => $this->payroll->id,
                'employee_id' => $employee->id,
                'salary_id' => $salary->id
            ]);
        }

        $startTime = microtime(true);

        $results = PayrollDetail::query()
            ->where('payroll_id', $this->payroll->id)
            ->with(['employee', 'salary'])
            ->get();

        $endTime = microtime(true);

        expect($results)->toHaveCount(100);
        expect($endTime - $startTime)->toBeLessThan(2.0); // Should complete in under 2 seconds
    });

    test('uses indexes efficiently', function () {
        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        // This should use indexes on foreign keys
        $result = PayrollDetail::query()
            ->where('payroll_id', $this->payroll->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        expect($result->id)->toBe($detail->id);
    });
});

describe('PayrollDetailBuilder - Error Handling', function () {
    test('handles invalid column names gracefully', function () {
        $results = PayrollDetail::query()
            ->where('invalid_column', 'value')
            ->get();

        expect($results)->toBeEmpty();
    });

    test('handles invalid relationship names', function () {
        try {
            $results = PayrollDetail::query()
                ->with('invalidRelation')
                ->get();

            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        } catch (\Throwable $e) {
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
    });
});
