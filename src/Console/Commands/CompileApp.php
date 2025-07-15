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
    protected $signature = 'compile {--a|only-assets}';

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
            'artisan',
            'composer.json',
            'composer.lock',
        ];

        $projectFilesConcant = join(',', $projectProductionFiles);

        $envProdPath = base_path('.env.production');
        $commands = [
            'delete_dir' => "rm -rf {$tauriResourcesAppPath}",
            'create_dir' => "mkdir {$tauriResourcesAppPath}",
            'set_env' => "cp {$envProdPath} {$tauriResourcesAppPath}/.env",
            'npm_build' => 'npm run build',
            'copy_project' => "cp -r ./{{$projectFilesConcant}} {$tauriResourcesAppPath}",
            'reset_database' => "rm -f {$tauriResourcesAppPath}/../dominominal.sqlite && touch {$tauriResourcesAppPath}/../dominominal.sqlite",
            'composer_install' => 'composer install --optimize-autoloader --no-dev -a',
            'artisan_optimize' => 'php artisan optimize',
            'filament_optimize' => 'php artisan filament:optimize',
            'tauri_build' => 'npx tauri build',
        ];

        if ($this->option('only-assets')) {
            $commands = [
                'npm_build' => 'npm run build',
            ];
        }

        $progressBar = $this->output->createProgressBar(count($commands));

        $originalPathCommands = ['delete_dir', 'create_dir', 'tauri_build', 'copy_project'];

        try {
            $progressBar->start();

            foreach ($commands as $commandKey => $command) {
                if ($commandKey === 'npm_build') {
                    $oldManifestFiles = $this->getManifestFiles();
                }

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

                if ($commandKey === 'npm_build' && !empty($oldManifestFiles)) {
                    $this->generateSplashscreen($oldManifestFiles);
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

    public function generateSplashscreen(array $oldManifestFiles): void
    {
        $manifestFiles = $this->getManifestFiles();

        $splashscreen = file_get_contents(base_path('public/splashscreen.html'));

        $splashscreen = str_replace($oldManifestFiles, $manifestFiles, $splashscreen);
        file_put_contents(base_path('public/splashscreen.html'), $splashscreen);
    }

    public function getManifestFiles(): array
    {
        $manifestPath = 'public/build/manifest.json';

        if (!file_exists($manifestPath)) {
            return ['', ''];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        return [$manifest['resources/js/app.js']['file'], $manifest['resources/css/app.css']['file']];
    }
}
