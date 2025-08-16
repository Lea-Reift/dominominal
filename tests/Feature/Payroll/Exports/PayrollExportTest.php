<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Exports\PayrollExport;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
    $this->payroll = Payroll::factory()->create(['company_id' => $this->company->id]);
});

describe('PayrollExport - Basic Functionality', function () {
    test('can create export instance with payroll', function () {
        $export = new PayrollExport($this->payroll->display);

        expect($export)->toBeInstanceOf(PayrollExport::class);
    });

    test('export generates correct view', function () {
        $export = new PayrollExport($this->payroll->display);
        $view = $export->view();

        expect($view)->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
    });

    test('export includes payroll details when present', function () {
        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $export = new PayrollExport($this->payroll->display);
        $view = $export->view();

        expect($view)->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
        expect($this->payroll->display->details)->not->toBeEmpty();
    });

    test('export implements required interfaces', function () {
        $export = new PayrollExport($this->payroll->display);

        expect($export)->toBeInstanceOf(\Maatwebsite\Excel\Concerns\FromView::class);
        expect($export)->toBeInstanceOf(\Maatwebsite\Excel\Concerns\ShouldAutoSize::class);
        expect($export)->toBeInstanceOf(\Maatwebsite\Excel\Concerns\WithDefaultStyles::class);
        expect($export)->toBeInstanceOf(\Maatwebsite\Excel\Concerns\WithStyles::class);
    });
});

describe('PayrollExport - Edge Cases', function () {
    test('export handles payroll with no employees', function () {
        $export = new PayrollExport($this->payroll->display);
        $view = $export->view();

        expect($view)->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
        expect($this->payroll->display->details)->toBeEmpty();
    });

    test('export handles empty payroll display', function () {
        $export = new PayrollExport($this->payroll->display);

        expect($export)->toBeInstanceOf(PayrollExport::class);
    });
});

describe('PayrollExport - File Generation', function () {
    test('can generate excel file', function () {
        PayrollDetail::factory()->create([
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
            'salary_id' => $this->salary->id
        ]);

        $export = new PayrollExport($this->payroll->display);
        $fileName = 'payroll-test-' . time() . '.xlsx';

        Excel::store($export, $fileName, 'local');

        expect(Storage::disk('local')->exists($fileName))->toBeTrue();

        // Clean up the test file
        Storage::disk('local')->delete($fileName);
    });
});

describe('PayrollExport - Integration', function () {
    test('export works with multiple employees', function () {
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

        $export = new PayrollExport($this->payroll->display);
        $view = $export->view();

        expect($view)->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
        expect($this->payroll->display->details)->toHaveCount(2);
    });

    test('export maintains payroll display structure', function () {
        $export = new PayrollExport($this->payroll->display);

        expect($export)->toBeInstanceOf(PayrollExport::class);
        expect($this->payroll->display)->toBeInstanceOf(\App\Support\ValueObjects\PayrollDisplay::class);
    });
});
