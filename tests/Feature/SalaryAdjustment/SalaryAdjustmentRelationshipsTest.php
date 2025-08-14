<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Payroll\Models\SalaryAdjustment;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollDetail;

use function Pest\Laravel\actingAs;

describe('SalaryAdjustment Relationships', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('salary adjustment can be associated with payrolls', function () {
        $adjustment = SalaryAdjustment::factory()->create();
        $payroll1 = Payroll::factory()->create();
        $payroll2 = Payroll::factory()->create();

        $adjustment->payrolls()->attach([$payroll1->id, $payroll2->id]);

        expect($adjustment->payrolls)->toHaveCount(2);
        expect($adjustment->payrolls->pluck('id')->toArray())
            ->toContain($payroll1->id)
            ->toContain($payroll2->id);
    });

    test('salary adjustment can be associated with payroll details', function () {
        $adjustment = SalaryAdjustment::factory()->create();
        $detail1 = PayrollDetail::factory()->create();
        $detail2 = PayrollDetail::factory()->create();

        $adjustment->payrollDetails()->attach([$detail1->id, $detail2->id]);

        expect($adjustment->payrollDetails)->toHaveCount(2);
        expect($adjustment->payrollDetails->pluck('id')->toArray())
            ->toContain($detail1->id)
            ->toContain($detail2->id);
    });

    test('detaching payroll removes association but keeps salary adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->create();
        $payroll = Payroll::factory()->create();

        $adjustment->payrolls()->attach($payroll->id);
        expect($adjustment->payrolls)->toHaveCount(1);

        $adjustment->payrolls()->detach($payroll->id);
        $adjustment->refresh();

        expect($adjustment->payrolls)->toHaveCount(0);
        expect(SalaryAdjustment::find($adjustment->id))->not->toBeNull();
        expect(Payroll::find($payroll->id))->not->toBeNull();
    });

    test('detaching payroll detail removes association but keeps salary adjustment', function () {
        $adjustment = SalaryAdjustment::factory()->create();
        $detail = PayrollDetail::factory()->create();

        $adjustment->payrollDetails()->attach($detail->id);
        expect($adjustment->payrollDetails)->toHaveCount(1);

        $adjustment->payrollDetails()->detach($detail->id);
        $adjustment->refresh();

        expect($adjustment->payrollDetails)->toHaveCount(0);
        expect(SalaryAdjustment::find($adjustment->id))->not->toBeNull();
        expect(PayrollDetail::find($detail->id))->not->toBeNull();
    });

    test('can sync payroll relationships', function () {
        $adjustment = SalaryAdjustment::factory()->create();
        $payroll1 = Payroll::factory()->create();
        $payroll2 = Payroll::factory()->create();
        $payroll3 = Payroll::factory()->create();

        $adjustment->payrolls()->attach([$payroll1->id, $payroll2->id]);
        expect($adjustment->payrolls)->toHaveCount(2);

        $adjustment->payrolls()->sync([$payroll2->id, $payroll3->id]);
        $adjustment->refresh();

        expect($adjustment->payrolls)->toHaveCount(2);
        expect($adjustment->payrolls->pluck('id')->toArray())
            ->toContain($payroll2->id)
            ->toContain($payroll3->id)
            ->not->toContain($payroll1->id);
    });

    test('can sync payroll detail relationships', function () {
        $adjustment = SalaryAdjustment::factory()->create();
        $detail1 = PayrollDetail::factory()->create();
        $detail2 = PayrollDetail::factory()->create();
        $detail3 = PayrollDetail::factory()->create();

        $adjustment->payrollDetails()->attach([$detail1->id, $detail2->id]);
        expect($adjustment->payrollDetails)->toHaveCount(2);

        $adjustment->payrollDetails()->sync([$detail2->id, $detail3->id]);
        $adjustment->refresh();

        expect($adjustment->payrollDetails)->toHaveCount(2);
        expect($adjustment->payrollDetails->pluck('id')->toArray())
            ->toContain($detail2->id)
            ->toContain($detail3->id)
            ->not->toContain($detail1->id);
    });
});