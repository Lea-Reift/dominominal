<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $setting = new Setting([
            'setting' => 'setup',
            'name' => 'is_completed',
            'value' => false,
        ]);

        if (!Schema::hasTable($setting->getTable())) {
            return;
        }

        $setting->save();
    }

    public function down(): void
    {
        Setting::where([
            'setting' => 'setup',
            'name' => 'is_completed',
        ])
            ->delete();
    }
};
