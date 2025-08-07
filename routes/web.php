<?php

/*
|--------------------------------------------------------------------------
| Terminal Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the Laravel Terminal package.
| These routes are automatically registered by the TerminalServiceProvider.
|
*/

use Illuminate\Support\Facades\Route;
use Recca0120\Terminal\Http\Controllers\TerminalController;

Route::get('/{view?}', [TerminalController::class, 'index'])
    ->name('index');

Route::post('/endpoint', [TerminalController::class, 'endpoint'])
    ->name('endpoint');

Route::get('/media/{file}', [TerminalController::class, 'media'])
    ->name('media')
    ->where('file', '.+');
