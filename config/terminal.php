<?php

return [
    // Enable/disable the terminal completely
    // true = always enabled
    // false = always disabled  
    // env('APP_DEBUG', false) = enabled only when APP_DEBUG is true
    'enabled' => env('APP_DEBUG', false),
    
    // IP addresses allowed to access terminal (only used if 'enabled' is not explicitly true/false)
    'whitelists' => ['127.0.0.1', '::1', '10.0.2.2', '192.168.1.1'],
    
    'route' => [
        'prefix' => 'terminal',
        'as' => 'terminal.',
        'middleware' => ['web'],
    ],
    'commands' => [
        \Recca0120\Terminal\Console\Commands\Artisan::class,
        \Recca0120\Terminal\Console\Commands\ArtisanTinker::class,
        \Recca0120\Terminal\Console\Commands\Cleanup::class,
        \Recca0120\Terminal\Console\Commands\Find::class,
        \Recca0120\Terminal\Console\Commands\Mysql::class,
        \Recca0120\Terminal\Console\Commands\Tail::class,
        \Recca0120\Terminal\Console\Commands\Vi::class,
        \Recca0120\Terminal\Console\Commands\Composer::class, // Added Composer support
        \Recca0120\Terminal\Console\Commands\Npm::class, // Added NPM support
    ],
];
