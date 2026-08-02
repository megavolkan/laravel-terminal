# Laravel Terminal - Enhanced Version

Laravel 11/12/13 compatible web terminal with full Composer support and Filament authentication.

## Features

- ✅ Laravel 11/12/13 compatibility
- ✅ Full Composer support, running in-process — no shell functions required
- ✅ Smart Tinker with auto-fixes for quotes and namespaces
- ✅ Filament authentication integration
- ✅ Shared hosting compatible
- ✅ No SSH required

## ⚠️ Upgrading — breaking changes

This version is **secure by default**. After upgrading you must take two steps or the terminal will be unreachable:

1. **Enable it explicitly.** The `enabled` flag no longer follows `APP_DEBUG`. Add to your `.env`:

   ```
   TERMINAL_ENABLED=true
   ```

2. **Check the IP whitelist.** The whitelist in `config/terminal.php` never actually ran in earlier versions — it does now. If your published config still lists `127.0.0.1` and friends, you will be locked out from your real IP. Either add your own IP or set `'whitelists' => []` to disable the IP check and rely on authentication middleware instead.

`composer.phar` is no longer needed and can be deleted from your project root.

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
'enabled' => env('TERMINAL_ENABLED', false),

'whitelists' => [],   // empty = no IP check; rely on the middleware below

'route' => [
    'prefix' => 'terminal',
    'as' => 'terminal.',
    'middleware' => ['web', 'filament.terminal'],
],
```

And enable it in `.env`:

```
TERMINAL_ENABLED=true
```

### 6. For Shared Hosting

Nothing extra to install. Composer runs inside the same PHP process via the
`composer/composer` library, so `exec`, `proc_open` and friends can stay
disabled. `composer.phar` is **not** used and is not needed.

## Usage

- Access terminal at: `/terminal`
- Must be logged into Filament admin panel first
- Composer: `composer install`, `require`, `remove`, `update`, `show`, `outdated`, `dump-autoload`
- Smart Tinker: `tinker User::count()`, `tinker config(app.name)`

## Security

The terminal executes arbitrary PHP, SQL and filesystem operations. Treat the
URL as equivalent to shell access and layer every control below:

- **Disabled by default** — requires `TERMINAL_ENABLED=true`
- **IP whitelist** — enforced per request by `AuthorizeTerminal` middleware; a
  blocked request gets a 404 so the terminal's existence is not disclosed
- **Authentication middleware** — always add one (see step 3) for production
- **Path confinement** — `vi`, `tail` and `find` cannot read or write outside
  the project root
- **Destructive commands gated** — `cleanup` requires an explicit `--force`

Do not rely on `APP_DEBUG` to gate the terminal; it is unrelated to access
control and is frequently left enabled by accident on shared hosts.

## Commands Available

- **Artisan**: All Laravel artisan commands
- **Tinker**: Interactive PHP with smart auto-corrections
- **Composer**: Full Composer functionality
- **System**: find, tail, cleanup, vi, mysql

## Troubleshooting

**Terminal returns 404.** Either `TERMINAL_ENABLED` is not `true`, or your IP is
not in `config/terminal.php`'s `whitelists`. Set `'whitelists' => []` to turn the
IP check off.

**`composer update` runs out of memory.** Full dependency resolution can need
512 MB or more, and many shared hosts forbid raising `memory_limit` at runtime.
`install`, `require` and `remove` are much lighter; for a large `update`, run it
locally and upload `composer.lock`, then run `composer install` here.

**A package fails to install.** Only Packagist dist (zip) packages are supported;
`dev-*` branches sourced from git need a git binary, which shared hosts lack.

## Credits

Enhanced version of [recca0120/laravel-terminal](https://github.com/recca0120/laravel-terminal) with Laravel 11/12 compatibility and additional features.