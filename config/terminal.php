<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Terminali Etkinleştir
    |--------------------------------------------------------------------------
    |
    | Terminal varsayılan olarak KAPALIDIR. Açmak için .env dosyasına
    | TERMINAL_ENABLED=true ekleyin.
    |
    | Bu ayar tek başına yeterli DEĞİLDİR: aşağıdaki 'whitelists' ve
    | 'middleware' kontrolleri de geçilmelidir. Terminal, sunucuda rastgele
    | PHP kodu ve SQL çalıştırabildiği için canlı ortamda mutlaka kimlik
    | doğrulamasıyla birlikte kullanılmalıdır.
    |
    */
    'enabled' => env('TERMINAL_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | IP Beyaz Listesi
    |--------------------------------------------------------------------------
    |
    | Terminale erişebilecek IP adresleri. Liste BOŞ bırakılırsa IP kontrolü
    | yapılmaz ve erişim yalnızca 'middleware' ayarına bağlı kalır.
    |
    | Not: Sunucu bir proxy/CDN arkasındaysa Laravel'in TrustProxies
    | ayarı doğru yapılmadan istemci IP'si güvenilir değildir.
    |
    */
    'whitelists' => [
        '127.0.0.1',
        '::1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Ayarları
    |--------------------------------------------------------------------------
    |
    | Canlı ortamda 'middleware' dizisine mutlaka bir kimlik doğrulama
    | katmanı ekleyin; örneğin ['web', 'auth'] veya kendi middleware'iniz.
    |
    */
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
        \Recca0120\Terminal\Console\Commands\Composer::class,
    ],
];
