# Laravel Terminal - Enhanced Version

Laravel 11/12 compatible web terminal with full Composer support and Filament authentication.

## Features

- ✅ Laravel 11/12 compatibility
- ✅ Full Composer support (all commands)
- ✅ Smart Tinker with auto-fixes for quotes and namespaces
- ✅ Filament authentication integration
- ✅ Shared hosting compatible
- ✅ No SSH required

## Installation

### 1. Install Package

```bash
composer config repositories.enhanced-terminal vcs https://github.com/megavolkan/laravel-terminal
composer require recca0120/terminal:dev-master
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="Recca0120\Terminal\TerminalServiceProvider"
```

### 3. Create Filament Auth Middleware

```bash
php artisan make:middleware FilamentTerminalAuth
```

Add this content to `app/Http/Middleware/FilamentTerminalAuth.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilamentTerminalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect('/admin/login');
        }

        $user = auth()->user();
        
        // Optional: Add role/permission checks
        /*
        if (!$user->hasRole('admin')) {
            return response('Access denied to terminal', 403);
        }
        */

        return $next($request);
    }
}
```

### 4. Register Middleware

In `bootstrap/app.php`, add:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'filament.terminal' => \App\Http\Middleware\FilamentTerminalAuth::class,
    ]);
})
```

### 5. Update Terminal Config

In `config/terminal.php`, set:

```php
'route' => [
    'prefix' => 'terminal',
    'as' => 'terminal.',
    'middleware' => ['web', 'filament.terminal'],
],
```

### 6. For Shared Hosting

Upload `composer.phar` to your project root:

```bash
curl -o composer.phar https://getcomposer.org/composer.phar
```

## Usage

- Access terminal at: `/terminal`
- Must be logged into Filament admin panel first
- All Composer commands available: `composer install`, `composer update`, etc.
- Smart Tinker: `tinker User::count()`, `tinker config(app.name)`

## Security

- Requires Filament authentication
- Add role/permission checks in middleware as needed
- Safe for shared hosting environments

## Commands Available

- **Artisan**: All Laravel artisan commands
- **Tinker**: Interactive PHP with smart auto-corrections
- **Composer**: Full Composer functionality
- **System**: find, tail, cleanup, vi, mysql

## Troubleshooting

If Composer commands fail, ensure `composer.phar` is in your project root or Composer is installed on the server.

## Credits

Enhanced version of [recca0120/laravel-terminal](https://github.com/recca0120/laravel-terminal) with Laravel 11/12 compatibility and additional features.