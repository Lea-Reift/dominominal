<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use App\Support\ValueObjects\Command as CommandVO;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Collection;
use RuntimeException;

class CompileAppCommand extends Command implements Isolatable
{
    protected $signature = 'compile {--a|assets} {--p|project} {--t|tauri} {--d|debug} {--x|no-upgrade}';

    protected bool $withGenerationOptions = false;
    public function handle()
    {
        $this->info('Compilando app para distribución...');

        $tauriResourcesAppPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, base_path('src-tauri/resources/app'));

        $envProdPath = base_path('.env.production');

        $commands = collect();

        $compilationKeys = config('app.compilation');
        $tauriCompilationFlags = $this->option('debug') ? '--debug --no-bundle' : '';

        $this->withGenerationOptions = $this->option('assets') || $this->option('project') || $this->option('tauri');
        $basePath = base_path();

        $assetsCommands = [
            new CommandVO('npm run build'),
            new CommandVO('php artisan filament:assets'),
            new CommandVO('php artisan generate-splash'),
        ];

        $projectProductionFiles = collect()
            ->merge([
                'bootstrap',
                'config',
                'database',
                'resources',
                'routes',
                'src',
                'artisan',
                'composer.json',
                'composer.lock',
                'storage',
                'dominominal.version.json',
            ])
            ->map(fn (string $path) => "'{$path}'")
            ->join(',');

        $migrateProjectCommands = [
            new CommandVO('php artisan compile:clear'),
            new CommandVO("git clone --depth=1 file://{$basePath} {$tauriResourcesAppPath}"),
            new CommandVO(<<<COMMAND
            powershell -NoProfile -Command "Get-ChildItem -Force | Where-Object { @({$projectProductionFiles}) -notcontains \$_.Name } | Remove-Item -Recurse -Force"
            COMMAND, $tauriResourcesAppPath),
            new CommandVO("cp {$envProdPath} {$tauriResourcesAppPath}/.env"),
            new CommandVO("cp -r {$basePath}/public {$tauriResourcesAppPath}/public"),
            new CommandVO('composer install --optimize-autoloader --no-dev -a', $tauriResourcesAppPath),
        ];

        $tauriCompileCommand = [
            new CommandVO(<<<COMMAND
            powershell -NoProfile -ExecutionPolicy Bypass -Command "& {
                \$env:TAURI_SIGNING_PRIVATE_KEY_PASSWORD = '{$compilationKeys['password']}';
                \$env:TAURI_SIGNING_PRIVATE_KEY = '{$compilationKeys['private_key']}';
                npx tauri build {$tauriCompilationFlags}
            }"
            COMMAND),
        ];

        $commands = $commands
            ->when($this->option('assets'), fn (Collection $collection) => $collection->merge($assetsCommands))
            ->when($this->option('project'), fn (Collection $collection) => $collection->merge($migrateProjectCommands))
            ->when($this->option('tauri'), fn (Collection $collection) => $collection->merge($tauriCompileCommand))
            ->unless(
                $this->withGenerationOptions,
                fn (Collection $collection) => $collection
                    ->merge($assetsCommands)
                    ->merge($migrateProjectCommands)
                    ->merge($tauriCompileCommand)
            );

        $productionEnvVars = $this->parseEnvFile(base_path('.env.production'));
        $outputCallback = function (string $type, string $output) {
            $this->line($output);
        };

        try {
            $commands->each(function (CommandVO $command) use ($productionEnvVars, $outputCallback) {
                $this->newLine();
                $this->info("Ejecutando: {$command->command}...");
                $this->newLine();

                $process = Process::forever()
                    ->path($command->path)
                    ->env($productionEnvVars)
                    ->run($command->command, $outputCallback);

                throw_if($process->failed(), RuntimeException::class, $command->command);
            });
        } catch (Exception $e) {
            $this->newLine();
            $this->error("El proceso {$e->getMessage()} falló");
            return Command::FAILURE;
        }

        $this->newLine();

        if (!$this->option('no-upgrade') && !$this->withGenerationOptions) {
            $this->updateAppSignature();
            $this->addGitRelease();
        }

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
        return system_version(fresh: true);
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

    protected function addGitRelease(): void
    {
        $currentVersion = $this->getCurrentVersion();

        $tag = "v{$currentVersion}";

        $commands = [
            'git add .',
            'git commit -m "Upgrade to version ' . $currentVersion . '"',
            'git push origin main',
            'git tag -a ' . $tag . ' -m ""',
            'git push origin ' . $tag,
            'gh release create ' . $tag . ' --latest --generate-notes ./src-tauri/target/release/bundle/nsis/Dominominal_' . $currentVersion . '_x64-setup.exe',
        ];

        foreach ($commands as $command) {
            Process::path(base_path())
                ->run($command, function (string $type, string $output) {
                    $this->line($output);
                });
        }

        $this->info('Release creado con exito');
    }
}
