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

Route::post('/endpoint', [TerminalController::class, 'endpoint'])
    ->name('endpoint');

Route::get('/media/{file}', [TerminalController::class, 'media'])
    ->name('media')
    ->where('file', '.+');

// Yakala-hepsini route en sonda olmalı. {view} yalnızca var olan görünümlerle
// sınırlandırıldı; aksi halde /terminal/rastgele isteği "View not found"
// istisnasıyla 500 döndürüyordu.
Route::get('/{view?}', [TerminalController::class, 'index'])
    ->name('index')
    ->where('view', 'index|panel');
