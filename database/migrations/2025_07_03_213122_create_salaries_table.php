<?php

declare(strict_types=1);

use App\Enums\SalaryDistributionFormatEnum;
use App\Enums\SalaryTypeEnum;
use App\Modules\Company\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Employee::class)->constrained();
            $table->decimal('amount', 10);
            $table->tinyInteger('type')->default(SalaryTypeEnum::BIWEEKLY);
            $table->tinyInteger('distribution_format')->default(SalaryDistributionFormatEnum::PERCENTAGE);
            $table->decimal('distribution_value')->default(50);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
