<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

class CompileApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Compilando app para distribución...');

        $tauriResourcesAppPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, base_path('src-tauri/resources/app'));

        $projectProductionFiles = [
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'src',
            'storage',
            '.env',
            'artisan',
            'composer.json',
            'composer.lock',
            'package-lock.json',
            'package.json',
            'vite.config.js',
        ];

        $projectFilesConcant = join(',', $projectProductionFiles);

        $commands = [
            'delete_dir' => "rm -rf {$tauriResourcesAppPath}",
            'create_dir' => "mkdir {$tauriResourcesAppPath}",
            'copy_project' => "cp -r ./{{$projectFilesConcant}} {$tauriResourcesAppPath}",
            'composer_install' => 'composer install --optimize-autoloader --no-dev -a',
            'npm_install' => 'npm install --omit=dev',
            'npm_build' => 'npm run build',
            'artisan_optimize' => 'php artisan optimize',
            'filament_optimize' => 'php artisan filament:optimize',
            'tauri_build' => 'npx tauri build',
        ];

        $progressBar = $this->output->createProgressBar(count($commands));

        $originalPathCommands = ['delete_dir', 'create_dir', 'tauri_build', 'copy_project'];

        try {
            $progressBar->start();

            foreach ($commands as $commandKey => $command) {
                $process = Process::timeout(0)
                    ->unless(
                        in_array($commandKey, $originalPathCommands),
                        fn (PendingProcess $process) => $process->path($tauriResourcesAppPath)
                    )
                    ->run($command);

                if ($process->failed()) {
                    $this->line('');
                    $this->error("{$command} | {$commandKey} con el error: {$process->errorOutput()}");
                    return Command::FAILURE;
                }
                $progressBar->advance();
            }
        } catch (Exception $e) {
            $this->line('');
            $this->error("El proceso {$e->getMessage()} falló");
            return Command::FAILURE;
        }

        $this->line('');

        $progressBar->finish();

        $this->info('Programa compilado con exito!!!!');
        return Command::SUCCESS;
    }
}
