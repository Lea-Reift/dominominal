<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearCompilationResourcesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compile:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears all resources/app folders in the tauri compile process';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directories = [
            'src-tauri\resources\app',
            'src-tauri\target\debug\resources\app',
            'src-tauri\target\release\resources\app',
        ];

        foreach ($directories as $directory) {
            $directory = base_path($directory);
            if (is_dir($directory)) {

                exec("rm -rf {$directory}/*");
            }
        }

        $this->info('Directorios eliminado con exito!!!');
        return Command::SUCCESS;
    }
}
