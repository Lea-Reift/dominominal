<?php

declare(strict_types=1);

use App\Modules\Company\Models\Employee;
use App\Modules\Company\Models\Salary;
use App\Modules\Payroll\Models\Payroll;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Payroll::class)->constrained();
            $table->foreignIdFor(Employee::class)->constrained();
            $table->foreignIdFor(Salary::class)->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
