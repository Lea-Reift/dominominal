<?php

declare(strict_types=1);

use App\Support\Pages\Setup;
use Illuminate\Support\Facades\Route;

Route::get('/', Setup::class);

Route::view('/splashscreen', 'support.pages.splashscreen');
