<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;

use function Pest\Laravel\actingAs;

describe('Companies Module Relationships', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    describe('Company relationships', function () {
        beforeEach(function () {
            $this->company = Company::factory()->create(['user_id' => $this->user->id]);
        });

        test('company belongs to user', function () {
            expect($this->company->user)->not->toBeNull();
            expect($this->company->user)->toBeInstanceOf(User::class);
            expect($this->company->user_id)->toBe($this->user->id);
        });

        test('company has many employees', function () {
            $employees = Employee::factory()->count(3)->create(['company_id' => $this->company->id]);

            $companyEmployees = $this->company->employees;

            expect($companyEmployees)->toHaveCount(3);
            $companyEmployees->each(function ($employee) {
                expect($employee)->toBeInstanceOf(Employee::class);
                expect($employee->company_id)->toBe($this->company->id);
            });
        });

        test('company employees relationship returns empty collection when no employees', function () {
            expect($this->company->employees)->toHaveCount(0);
            expect($this->company->employees)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });

        test('company has many payrolls', function () {
            $payrolls = Payroll::factory()->count(2)->create(['company_id' => $this->company->id]);

            $companyPayrolls = $this->company->payrolls;

            expect($companyPayrolls)->toHaveCount(2);
            $companyPayrolls->each(function ($payroll) {
                expect($payroll)->toBeInstanceOf(Payroll::class);
                expect($payroll->company_id)->toBe($this->company->id);
            });
        });

        test('company payrolls relationship returns empty collection when no payrolls', function () {
            expect($this->company->payrolls)->toHaveCount(0);
            expect($this->company->payrolls)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });

        test('can load employees with company', function () {
            Employee::factory()->count(2)->create(['company_id' => $this->company->id]);

            $companyWithEmployees = Company::with('employees')->find($this->company->id);

            expect($companyWithEmployees->employees)->toHaveCount(2);
            expect($companyWithEmployees->relationLoaded('employees'))->toBeTrue();
        });

        test('can load payrolls with company', function () {
            Payroll::factory()->count(2)->create(['company_id' => $this->company->id]);

            $companyWithPayrolls = Company::with('payrolls')->find($this->company->id);

            expect($companyWithPayrolls->payrolls)->toHaveCount(2);
            expect($companyWithPayrolls->relationLoaded('payrolls'))->toBeTrue();
        });

        test('can load user with company', function () {
            $companyWithUser = Company::with('user')->find($this->company->id);

            expect($companyWithUser->user)->toBeInstanceOf(User::class);
            expect($companyWithUser->relationLoaded('user'))->toBeTrue();
        });
    });

    describe('Employee relationships', function () {
        beforeEach(function () {
            $this->company = Company::factory()->create(['user_id' => $this->user->id]);
            $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
        });

        test('employee belongs to company', function () {
            expect($this->employee->company)->not->toBeNull();
            expect($this->employee->company)->toBeInstanceOf(Company::class);
            expect($this->employee->company_id)->toBe($this->company->id);
        });

        test('employee has one salary', function () {
            $salary = Salary::factory()->create(['employee_id' => $this->employee->id]);

            $employeeSalary = $this->employee->salary;

            expect($employeeSalary)->not->toBeNull();
            expect($employeeSalary)->toBeInstanceOf(Salary::class);
            expect($employeeSalary->employee_id)->toBe($this->employee->id);
        });

        test('employee salary returns null when no salary exists', function () {
            expect($this->employee->salary)->toBeNull();
        });

        test('employee returns latest salary when multiple salaries exist', function () {
            $firstSalary = Salary::factory()->create([
                'employee_id' => $this->employee->id,
                'created_at' => now()->subDays(5),
            ]);

            $latestSalary = Salary::factory()->create([
                'employee_id' => $this->employee->id,
                'created_at' => now(),
            ]);

            expect($this->employee->salary->id)->toBe($latestSalary->id);
            expect($this->employee->salary->id)->not->toBe($firstSalary->id);
        });

        test('can load company with employee', function () {
            $employeeWithCompany = Employee::with('company')->find($this->employee->id);

            expect($employeeWithCompany->company)->toBeInstanceOf(Company::class);
            expect($employeeWithCompany->relationLoaded('company'))->toBeTrue();
        });

        test('can load salary with employee', function () {
            Salary::factory()->create(['employee_id' => $this->employee->id]);

            $employeeWithSalary = Employee::with('salary')->find($this->employee->id);

            expect($employeeWithSalary->salary)->toBeInstanceOf(Salary::class);
            expect($employeeWithSalary->relationLoaded('salary'))->toBeTrue();
        });
    });

    describe('Salary relationships', function () {
        beforeEach(function () {
            $this->company = Company::factory()->create(['user_id' => $this->user->id]);
            $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
            $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
        });

        test('salary belongs to employee', function () {
            expect($this->salary->employee)->not->toBeNull();
            expect($this->salary->employee)->toBeInstanceOf(Employee::class);
            expect($this->salary->employee_id)->toBe($this->employee->id);
        });

        test('can load employee with salary', function () {
            $salaryWithEmployee = Salary::with('employee')->find($this->salary->id);

            expect($salaryWithEmployee->employee)->toBeInstanceOf(Employee::class);
            expect($salaryWithEmployee->relationLoaded('employee'))->toBeTrue();
        });

        test('can access company through employee relationship', function () {
            $salaryWithEmployee = Salary::with('employee.company')->find($this->salary->id);

            expect($salaryWithEmployee->employee->company)->toBeInstanceOf(Company::class);
            expect($salaryWithEmployee->employee->company->id)->toBe($this->company->id);
        });
    });

    describe('Cascading relationships', function () {
        test('can load full hierarchy: company -> employees -> salaries', function () {
            $company = Company::factory()->create(['user_id' => $this->user->id]);
            $employees = Employee::factory()->count(2)->create(['company_id' => $company->id]);

            $employees->each(function ($employee) {
                Salary::factory()->create(['employee_id' => $employee->id]);
            });

            $fullCompany = Company::with('employees.salary')->find($company->id);

            expect($fullCompany->employees)->toHaveCount(2);
            $fullCompany->employees->each(function ($employee) {
                expect($employee->salary)->not->toBeNull();
                expect($employee->salary)->toBeInstanceOf(Salary::class);
            });
        });

        test('maintains data consistency across relationships', function () {
            $company = Company::factory()->create(['user_id' => $this->user->id]);
            $employee = Employee::factory()->create(['company_id' => $company->id]);
            $salary = Salary::factory()->create(['employee_id' => $employee->id]);

            expect($salary->employee->company->id)->toBe($company->id);
            expect($employee->company->id)->toBe($company->id);
            expect($company->employees->first()->id)->toBe($employee->id);
        });

        test('can filter employees by company through relationship', function () {
            $company1 = Company::factory()->create(['user_id' => $this->user->id]);
            $company2 = Company::factory()->create(['user_id' => $this->user->id]);

            $employee1 = Employee::factory()->create(['company_id' => $company1->id]);
            $employee2 = Employee::factory()->create(['company_id' => $company2->id]);

            $company1Employees = Employee::whereHas('company', function ($query) use ($company1) {
                $query->where('id', $company1->id);
            })->get();

            expect($company1Employees)->toHaveCount(1);
            expect($company1Employees->first()->id)->toBe($employee1->id);
        });

        test('can filter salaries by company through nested relationship', function () {
            $company1 = Company::factory()->create(['user_id' => $this->user->id]);
            $company2 = Company::factory()->create(['user_id' => $this->user->id]);

            $employee1 = Employee::factory()->create(['company_id' => $company1->id]);
            $employee2 = Employee::factory()->create(['company_id' => $company2->id]);

            $salary1 = Salary::factory()->create(['employee_id' => $employee1->id]);
            $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);

            $company1Salaries = Salary::whereHas('employee.company', function ($query) use ($company1) {
                $query->where('id', $company1->id);
            })->get();

            expect($company1Salaries)->toHaveCount(1);
            expect($company1Salaries->first()->id)->toBe($salary1->id);
        });
    });

    describe('Relationship constraints and edge cases', function () {
        test('employee without company throws error on access', function () {
            $employee = new Employee([
                'name' => 'Test',
                'surname' => 'Employee',
                'job_title' => 'Tester',
                'address' => 'Test Address',
                'email' => 'test@test.com',
            ]);
            $employee->company_id = 999999; // Non-existent company

            expect($employee->company)->toBeNull();
        });

        test('salary without employee throws error on access', function () {
            $salary = new Salary([
                'amount' => 1000.0,
                'type' => \App\Enums\SalaryTypeEnum::MONTHLY,
                'distribution_format' => \App\Enums\SalaryDistributionFormatEnum::ABSOLUTE,
                'distribution_value' => 30.0,
            ]);
            $salary->employee_id = 999999; // Non-existent employee

            expect($salary->employee)->toBeNull();
        });

        test('company can exist without employees', function () {
            $company = Company::factory()->create(['user_id' => $this->user->id]);

            expect($company->employees)->toHaveCount(0);
            expect($company->employees)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });

        test('employee can exist without salary', function () {
            $company = Company::factory()->create(['user_id' => $this->user->id]);
            $employee = Employee::factory()->create(['company_id' => $company->id]);

            expect($employee->salary)->toBeNull();
        });
    });
});
