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
    protected $signature = 'compile {--a|assets} {--p|project} {--t|tauri} {--d|debug}';

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

        $commands = [];
        $assetsCommands = [
            'npm_build' => 'npm run build',
            'generate_splash' => 'php artisan generate-splash',
        ];

        $migrateProjectCommands = [
            'delete_dir' => "rm -rf {$tauriResourcesAppPath}",
            'create_dir' => "mkdir {$tauriResourcesAppPath}",
            'set_env' => "cp {$envProdPath} {$tauriResourcesAppPath}/.env",
            'copy_project' => "cp -r ./{{$projectFilesConcant}} {$tauriResourcesAppPath}",
            'remove_dev_database' => 'rm -f ./database/dominominal.sqlite',
            'composer_install' => 'composer install --optimize-autoloader --no-dev -a',
            'artisan_optimize' => 'php artisan optimize',
            'filament_optimize' => 'php artisan filament:optimize',
        ];

        $tauriCompileCommand = [
            'tauri_build' => 'npx tauri build' . ($this->option('debug') ? ' --debug --no-bundle' : ''),
        ];

        if ($this->option('assets')) {
            $commands = [...$commands, ...$assetsCommands];
        }

        if ($this->option('project')) {
            $commands = [...$commands, ...$migrateProjectCommands];
        }

        if ($this->option('tauri')) {
            $commands = [...$commands, ...$tauriCompileCommand];
        }

        if (empty($commands)) {
            $commands = [
                ...$assetsCommands,
                ...$migrateProjectCommands,
                ...$tauriCompileCommand,
            ];
        }

        $originalPathCommands = [
            'delete_dir',
            'create_dir',
            'tauri_build',
            'copy_project',
            'generate_splash',
        ];

        try {

            foreach ($commands as $commandKey => $command) {
                $this->newLine();
                $this->info("Ejecutando: {$command}...");
                $this->newLine();

                $process = Process::timeout(0)
                    ->unless(
                        in_array($commandKey, $originalPathCommands),
                        fn (PendingProcess $process) => $process->path($tauriResourcesAppPath)
                    )
                    ->run($command, function (string $type, string $output) {
                        $this->line($output);
                    });

                if ($process->failed()) {
                    return Command::FAILURE;
                }
            }
        } catch (Exception $e) {
            $this->newLine();
            $this->error("El proceso {$e->getMessage()} falló");
            return Command::FAILURE;
        }

        $this->newLine();

        $this->info('Programa compilado con exito!!!!');
        return Command::SUCCESS;
    }
}
