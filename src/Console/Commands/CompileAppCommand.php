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
            'filament_assets' => 'php artisan filament:assets',
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

            if (!$this->option('debug')) {
                $this->upgradeAppVersion();
            }
        }

        if (empty($commands)) {
            $commands = [
                ...$assetsCommands,
                ...$migrateProjectCommands,
                ...$tauriCompileCommand,
            ];

            if (!$this->option('debug')) {
                $this->upgradeAppVersion();
            }
        }

        if (empty(array_diff_assoc($commands, $assetsCommands))) {
            $commands['copy_to_debug_target'] = "rm -rf {$tauriDebugAppPath}/public && " .
                "cp -r ./public {$tauriDebugAppPath}/public";
        }

        $originalPathCommands = [
            'delete_dir',
            'create_dir',
            'tauri_build',
            'copy_project',
            'generate_splash',
            'clear_compilation_assets',
            'copy_to_debug_target',
            'filament_assets',
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

        $this->updateAppSignature();
        $this->addGitTag();
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

    protected function getCurrentVersion(): string
    {
        return json_decode(file_get_contents(base_path('dominominal.version.json')))->version ?? '0.1.0';
    }

    protected function upgradeAppVersion(): void
    {
        $currentVersion = $this->getCurrentVersion();

        $files = [
            base_path('dominominal.version.json'),
            base_path('src-tauri\tauri.conf.json'),
            base_path('src-tauri\Cargo.toml'),
        ];

        preg_match('/(.*?)(\d+)$/', $currentVersion, $matches);
        $newVersion = $matches[1] . ($matches[2] + 1);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $newContent = str_replace($currentVersion, $newVersion, $content);
            file_put_contents($file, $newContent);
        }
    }

    protected function updateAppSignature(): void
    {
        $currentVersion = $this->getCurrentVersion();
        $signatureFilePath = base_path("src-tauri/target/release/bundle/nsis/Dominominal_{$currentVersion}_x64-setup.exe.sig");

        if (!file_exists($signatureFilePath)) {
            $this->error("Signature file not found: {$signatureFilePath}");
            return;
        }

        $signature = file_get_contents($signatureFilePath);
        $versionFilePath = base_path('dominominal.version.json');
        $versionData = json_decode(file_get_contents($versionFilePath), true);

        $versionData['platforms']['windows-x86_64']['signature'] = $signature;

        file_put_contents($versionFilePath, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function addGitTag(): void
    {
        $currentVersion = $this->getCurrentVersion();

        $tag = "v{$currentVersion}";


        $commands = [
            'git add .',
            "git commit -m 'Upgrade to version {$currentVersion}'",
            "git tag -a {$tag}",
            "git push origin {$tag}",
        ];

        foreach ($commands as $command) {
            Process::path(base_path())->run($command);
        }

        $this->info('Tag creada con exito');
    }
}
