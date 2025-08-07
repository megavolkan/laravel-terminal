<?php

return [
    'enabled' => env('APP_DEBUG', true),
    'whitelists' => ['127.0.0.1', '::1', '10.0.2.2', '192.168.1.1'], // Add your local IPs
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
    ],
];
