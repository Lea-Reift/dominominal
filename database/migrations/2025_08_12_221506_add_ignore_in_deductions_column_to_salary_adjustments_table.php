<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('salary_adjustments', function (Blueprint $table) {
            $table->boolean('ignore_in_deductions')->after('requires_custom_value')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_adjustments', function (Blueprint $table) {
            $table->dropColumn('ignore_in_deductions');
        });
    }
};
