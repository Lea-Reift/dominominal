<?php

declare(strict_types=1);

use App\Enums\SalaryDistributionFormatEnum;
use App\Enums\SalaryTypeEnum;
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Support\ValueObjects\SalaryDistribution;

use function Pest\Laravel\{actingAs, assertDatabaseHas, assertSoftDeleted};

describe('Salary Model Operations', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);

        $this->company = Company::factory()->create(['user_id' => $this->user->id]);
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    });

    test('can create salary with required fields', function () {
        $salary = Salary::create([
            'employee_id' => $this->employee->id,
            'amount' => 3000.00,
            'type' => SalaryTypeEnum::MONTHLY,
            'distribution_format' => SalaryDistributionFormatEnum::ABSOLUTE,
            'distribution_value' => 30.0,
        ]);

        expect($salary)->not->toBeNull();
        expect($salary->employee_id)->toBe($this->employee->id);
        expect($salary->amount)->toBe(3000.0);
        expect($salary->type)->toBe(SalaryTypeEnum::MONTHLY);
        expect($salary->distribution_format)->toBe(SalaryDistributionFormatEnum::ABSOLUTE);
        expect($salary->distribution_value)->toBe(30.0);

        assertDatabaseHas(Salary::class, [
            'employee_id' => $this->employee->id,
            'amount' => 3000.0,
            'type' => SalaryTypeEnum::MONTHLY->value,
            'distribution_format' => SalaryDistributionFormatEnum::ABSOLUTE->value,
            'distribution_value' => 30.0,
        ]);
    });

    test('can create salary with different types', function () {
        $monthlySalary = Salary::create([
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'type' => SalaryTypeEnum::MONTHLY,
            'distribution_format' => SalaryDistributionFormatEnum::ABSOLUTE,
            'distribution_value' => 30.0,
        ]);

        $hourlySalary = Salary::create([
            'employee_id' => $this->employee->id,
            'amount' => 25.50,
            'type' => SalaryTypeEnum::BIWEEKLY,
            'distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE,
            'distribution_value' => 8.0,
        ]);

        expect($monthlySalary->type)->toBe(SalaryTypeEnum::MONTHLY);
        expect($hourlySalary->type)->toBe(SalaryTypeEnum::BIWEEKLY);
    });

    test('can create salary with different distribution formats', function () {
        $daysSalary = Salary::create([
            'employee_id' => $this->employee->id,
            'amount' => 4000.00,
            'type' => SalaryTypeEnum::MONTHLY,
            'distribution_format' => SalaryDistributionFormatEnum::ABSOLUTE,
            'distribution_value' => 30.0,
        ]);

        $hoursSalary = Salary::create([
            'employee_id' => $this->employee->id,
            'amount' => 2400.00,
            'type' => SalaryTypeEnum::MONTHLY,
            'distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE,
            'distribution_value' => 160.0,
        ]);

        expect($daysSalary->distribution_format)->toBe(SalaryDistributionFormatEnum::ABSOLUTE);
        expect($hoursSalary->distribution_format)->toBe(SalaryDistributionFormatEnum::PERCENTAGE);
    });

    test('can update salary amount', function () {
        $salary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 2500.00,
        ]);

        $salary->update(['amount' => 3500.00]);

        expect($salary->amount)->toBe(3500.0);
        assertDatabaseHas(Salary::class, [
            'id' => $salary->id,
            'amount' => 3500.0,
        ]);
    });

    test('can update salary type', function () {
        $salary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'type' => SalaryTypeEnum::MONTHLY,
        ]);

        $salary->update(['type' => SalaryTypeEnum::BIWEEKLY]);

        expect($salary->type)->toBe(SalaryTypeEnum::BIWEEKLY);
    });

    test('can update distribution settings', function () {
        $salary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'distribution_format' => SalaryDistributionFormatEnum::ABSOLUTE,
            'distribution_value' => 30.0,
        ]);

        $salary->update([
            'distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE,
            'distribution_value' => 160.0,
        ]);

        expect($salary->distribution_format)->toBe(SalaryDistributionFormatEnum::PERCENTAGE);
        expect($salary->distribution_value)->toBe(160.0);
    });

    test('can soft delete salary', function () {
        $salary = Salary::factory()->create(['employee_id' => $this->employee->id]);

        $salary->delete();

        assertSoftDeleted($salary);
        expect(Salary::find($salary->id))->toBeNull();
        expect(Salary::withTrashed()->find($salary->id))->not->toBeNull();
    });

    test('can restore soft deleted salary', function () {
        $salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
        $salary->delete();

        $salary->restore();

        expect(Salary::find($salary->id))->not->toBeNull();
        expect($salary->trashed())->toBeFalse();
    });

    test('can force delete salary', function () {
        $salary = Salary::factory()->create(['employee_id' => $this->employee->id]);
        $salaryId = $salary->id;

        $salary->forceDelete();

        expect(Salary::withTrashed()->find($salaryId))->toBeNull();
    });

    test('can handle decimal amounts correctly', function () {
        $testAmounts = [
            1500.50,
            999.99,
            10000.01,
            0.50,
        ];

        foreach ($testAmounts as $amount) {
            $salary = Salary::factory()->create([
                'employee_id' => $this->employee->id,
                'amount' => $amount,
            ]);

            expect($salary->amount)->toBe($amount);
        }
    });

    test('distribution attribute works with SalaryDistribution value object', function () {
        $salary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'distribution_format' => SalaryDistributionFormatEnum::ABSOLUTE,
            'distribution_value' => 30.0,
        ]);

        $distribution = $salary->distribution;

        expect($distribution)->toBeInstanceOf(SalaryDistribution::class);
        expect($distribution->format)->toBe(SalaryDistributionFormatEnum::ABSOLUTE);
        expect($distribution->value)->toBe(30.0);
    });

    test('can set distribution using SalaryDistribution value object', function () {
        $distribution = SalaryDistribution::make(
            SalaryDistributionFormatEnum::PERCENTAGE,
            160.0
        );

        $salary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'distribution' => $distribution,
        ]);

        expect($salary->distribution_format)->toBe(SalaryDistributionFormatEnum::PERCENTAGE);
        expect($salary->distribution_value)->toBe(160.0);
    });

    test('maintains referential integrity with employee', function () {
        $salary = Salary::factory()->create(['employee_id' => $this->employee->id]);

        expect($salary->employee)->not->toBeNull();
        expect($salary->employee->id)->toBe($this->employee->id);
        expect($salary->employee_id)->toBe($this->employee->id);
    });

    test('can create multiple salaries for same employee', function () {
        $salary1 = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 2000.00,
        ]);

        $salary2 = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 2500.00,
        ]);

        expect($salary1->employee_id)->toBe($this->employee->id);
        expect($salary2->employee_id)->toBe($this->employee->id);
        expect($salary1->id)->not->toBe($salary2->id);
    });

    test('can change employee assignment', function () {
        $anotherEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $salary = Salary::factory()->create(['employee_id' => $this->employee->id]);

        $salary->update(['employee_id' => $anotherEmployee->id]);

        expect($salary->employee_id)->toBe($anotherEmployee->id);
        expect($salary->employee->id)->toBe($anotherEmployee->id);
    });

    test('can handle large salary amounts', function () {
        $largeSalary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 99999.99,
        ]);

        expect($largeSalary->amount)->toBe(99999.99);
    });

    test('can handle zero salary amount', function () {
        $zeroSalary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 0.0,
        ]);

        expect($zeroSalary->amount)->toBe(0.0);
    });

    test('can handle distribution values with decimals', function () {
        $salary = Salary::factory()->create([
            'employee_id' => $this->employee->id,
            'distribution_format' => SalaryDistributionFormatEnum::PERCENTAGE,
            'distribution_value' => 160.5,
        ]);

        expect($salary->distribution_value)->toBe(160.5);
        expect($salary->distribution->value)->toBe(160.5);
    });
});
