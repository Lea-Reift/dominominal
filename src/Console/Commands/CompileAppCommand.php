<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

class CompileAppCommand extends Command
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

        $tauriDebugAppPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, base_path('src-tauri/target/debug/resources/app'));
        $tauriReleaseAppPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, base_path('src-tauri/target/release/resources/app'));

        $projectProductionFiles = [
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'src',
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
            'clear_compilation_assets' => 'php artisan compile:clear',
            'set_env' => "cp {$envProdPath} {$tauriResourcesAppPath}/.env",
            'copy_project' => "cp -r ./{{$projectFilesConcant}} {$tauriResourcesAppPath}",
            'add_storage_folders' => "mkdir {$tauriResourcesAppPath}\\storage\\framework\\sessions {$tauriResourcesAppPath}\\storage\\framework\\cache {$tauriResourcesAppPath}\\storage\\framework\\views",
            'remove_dev_database' => 'rm -f ./database/dominominal.sqlite',
            'composer_install' => 'composer install --optimize-autoloader --no-dev -a',
            'copy_project_to_debug' => "xcopy {$tauriResourcesAppPath} {$tauriDebugAppPath} /h /i /c /k /e /r /y",
            'copy_project_to_release' => "xcopy {$tauriResourcesAppPath} {$tauriReleaseAppPath} /h /i /c /k /e /r /y",
        ];

        $compilationKeys = config('app.compilation');
        $tauriCompilationFlags = $this->option('debug') ? '--debug --no-bundle' : '';

        $tauriCompileCommand = [
            'tauri_build' => <<<COMMAND
            powershell -NoProfile -ExecutionPolicy Bypass -Command "& {
                \$env:TAURI_SIGNING_PRIVATE_KEY_PASSWORD = '{$compilationKeys['password']}';
                \$env:TAURI_SIGNING_PRIVATE_KEY = '{$compilationKeys['private_key']}';
                npx tauri build {$tauriCompilationFlags}
            }"
            COMMAND,
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
            'clear_compilation_assets'
        ];

        $productionEnvVars = $this->parseEnvFile(base_path('.env.production'));

        try {

            $basePath = base_path();
            foreach ($commands as $commandKey => $command) {
                $this->newLine();
                $this->info("Ejecutando: {$command}...");
                $this->newLine();

                $process = Process::timeout(0)
                    ->unless(
                        in_array($commandKey, $originalPathCommands),
                        fn (PendingProcess $process) => $process->path($tauriResourcesAppPath)
                    )
                    ->when(
                        in_array($commandKey, $originalPathCommands),
                        fn (PendingProcess $process) => $process->path($basePath)
                    )
                    ->env($productionEnvVars)
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

    public function parseEnvFile(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $env[$key] = $value;
        }

        return $env;
    }
}
