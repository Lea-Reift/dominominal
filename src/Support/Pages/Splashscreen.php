<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Filament\Pages\Page;

class Splashscreen extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'support.pages.splashscreen';
}
