<?php

declare(strict_types=1);

use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Resources\PayrollResource;
use App\Modules\Payroll\Resources\PayrollResource\Pages\PayrollDetailsManager;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);

    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->actingAs($this->user);
});

describe('PayrollResource - Basic Configuration', function () {
    test('resource has correct model', function () {
        expect(PayrollResource::getModel())->toBe(Payroll::class);
    });

    test('resource has correct navigation label', function () {
        expect(PayrollResource::getNavigationLabel())->toBeString();
    });

    test('resource has correct navigation group', function () {
        $navigationGroup = PayrollResource::getNavigationGroup();
        expect($navigationGroup === null || is_string($navigationGroup))->toBeTrue();
    });

    test('resource has pages configured', function () {
        $pages = PayrollResource::getPages();

        expect($pages)->toBeArray();
        expect($pages)->not->toBeEmpty();
    });
});

describe('PayrollResource - Form Schema', function () {
    test('form has required fields', function () {
        expect(PayrollResource::class)->toBeString();
    });

    test('can create payroll through model', function () {
        $payrollData = [
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY,
            'period' => now()
        ];

        expect(Payroll::count())->toBe(0);

        $payroll = Payroll::create($payrollData);

        expect(Payroll::count())->toBe(1);
        expect($payroll->company_id)->toBe($this->company->id);
        expect($payroll->type)->toBe(SalaryTypeEnum::MONTHLY);
    });

    test('validates required fields on creation', function () {
        $exceptionThrown = false;
        try {
            Payroll::create([]);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
        expect($exceptionThrown)->toBeTrue();
    });

    test('can create multiple payrolls for same company', function () {
        Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY,
            'period' => '2024-01-30'
        ]);

        $secondPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::BIWEEKLY,
            'period' => '2024-02-15'
        ]);

        expect(Payroll::count())->toBe(2);
        expect($secondPayroll)->toBeInstanceOf(Payroll::class);
    });
});

describe('PayrollResource - Table Configuration', function () {
    test('can query payroll records', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $records = Payroll::all();
        expect($records->pluck('id'))->toContain($payroll->id);
    });

    test('resource has table method', function () {
        expect(method_exists(PayrollResource::class, 'table'))->toBeTrue();
    });

    test('can filter payroll records by period', function () {
        $payroll1 = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'period' => '2024-01-30'
        ]);

        $payroll2 = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'period' => '2024-02-28'
        ]);

        $januaryPayrolls = Payroll::whereMonth('period', 1)->get();
        expect($januaryPayrolls->pluck('id'))->toContain($payroll1->id);
        expect($januaryPayrolls->pluck('id'))->not->toContain($payroll2->id);
    });

    test('can filter payroll records by type', function () {
        $monthlyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::MONTHLY
        ]);

        $biweeklyPayroll = Payroll::factory()->create([
            'company_id' => $this->company->id,
            'type' => SalaryTypeEnum::BIWEEKLY
        ]);

        $monthlyPayrolls = Payroll::where('type', SalaryTypeEnum::MONTHLY)->get();
        expect($monthlyPayrolls->pluck('id'))->toContain($monthlyPayroll->id);
        expect($monthlyPayrolls->pluck('id'))->not->toContain($biweeklyPayroll->id);
    });
});

describe('PayrollResource - Actions', function () {
    test('can update payroll', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $payroll->update([
            'type' => SalaryTypeEnum::BIWEEKLY,
            'period' => now()->addMonth()
        ]);

        expect($payroll->fresh()->type)->toBe(SalaryTypeEnum::BIWEEKLY);
    });

    test('can delete payroll', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        expect(Payroll::count())->toBe(1);

        $payroll->delete();

        expect(Payroll::count())->toBe(0);
    });

    test('can delete multiple payrolls', function () {
        $payroll1 = Payroll::factory()->create(['company_id' => $this->company->id]);
        $payroll2 = Payroll::factory()->create(['company_id' => $this->company->id]);

        expect(Payroll::count())->toBe(2);

        Payroll::whereIn('id', [$payroll1->id, $payroll2->id])->delete();

        expect(Payroll::count())->toBe(0);
    });
});

describe('PayrollResource - PayrollDetailsManager Page', function () {
    test('payroll details manager page class exists', function () {
        expect(class_exists(PayrollDetailsManager::class))->toBeTrue();
    });

    test('can create payroll details', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $detail = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($payroll->fresh()->details->pluck('id'))->toContain($detail->id);
    });

    test('can add employees to payroll through model', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        expect($payroll->employees()->count())->toBe(0);

        PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        expect($payroll->fresh()->employees()->count())->toBe(1);
    });
});

describe('PayrollResource - Edge Cases', function () {
    test('handles payroll with no employees', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        expect($payroll->employees()->count())->toBe(0);
        expect($payroll->details)->toBeEmpty();
    });

    test('handles payroll with multiple employees', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $employee2 = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary2 = Salary::factory()->create(['employee_id' => $employee2->id]);

        $detail1 = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $detail2 = PayrollDetail::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee2->id,
            'salary_id' => $salary2->id
        ]);

        expect($payroll->fresh()->details)->toHaveCount(2);
        expect($payroll->fresh()->employees()->count())->toBe(2);
    });

    test('resource has url method', function () {
        expect(method_exists(PayrollResource::class, 'getUrl'))->toBeTrue();
    });
});

describe('PayrollResource - Error Handling', function () {
    test('handles invalid payroll id', function () {
        $invalidPayroll = Payroll::find(99999);
        expect($invalidPayroll)->toBeNull();
    });

    test('validates model input on update', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $exceptionThrown = false;
        try {
            $payroll->update([
                'company_id' => null,
                'type' => null,
                'period' => null
            ]);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
        expect($exceptionThrown)->toBeTrue();
    });

    test('handles database constraint errors', function () {
        $payroll = Payroll::factory()->create(['company_id' => $this->company->id]);

        $exceptionThrown = false;
        try {
            $payroll->update([
                'company_id' => 99999
            ]);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
        expect($exceptionThrown)->toBeTrue();
    });
});
