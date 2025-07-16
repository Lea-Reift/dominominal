<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSplashscreenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-splash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates project splashscreen html';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $html = view('splashscreen')->render();
        file_put_contents(public_path('splashscreen.html'), str_replace('http://localhost:8000', '.', $html));
        $this->info('splashscreen.html generado correctamente en public/');
    }
}
