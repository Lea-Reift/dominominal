<?php

declare(strict_types=1);

use App\Modules\Payroll\Models\PayrollDetail;
use App\Modules\Payroll\Models\SalaryAdjustment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_detail_salary_adjustment', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PayrollDetail::class);
            $table->foreignIdFor(SalaryAdjustment::class);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_detail_salary_adjustment');
    }
};
