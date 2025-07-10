<?php

declare(strict_types=1);

use App\Enums\PayrollTypeEnum;
use App\Modules\Company\Models\Company;
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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained();
            $table->foreignIdFor(Payroll::class, 'monthly_payroll_id')->nullable()->constrained();
            $table->tinyInteger('type')->default(PayrollTypeEnum::MONTHLY);
            $table->date('period');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
