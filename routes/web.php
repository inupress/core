<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inupress\Core\Http\HomeController;

// A route file loaded by Spatie package tools gets no middleware group of its own: route:cache
// cannot serialize closures, and without the web group there is no session (AGENTS.md).
Route::get('/', HomeController::class)->middleware('web')->name('inupress.home');
