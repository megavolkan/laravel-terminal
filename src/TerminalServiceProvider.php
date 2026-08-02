<?php

namespace Recca0120\Terminal;

use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Recca0120\Terminal\Http\Middleware\AuthorizeTerminal;
use Exception;

class TerminalServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        try {
            $config = $this->app['config']['terminal'] ?? [];

            // Route'lar yalnızca terminal etkinken kaydedilir. İsteğe özel
            // kontroller (IP beyaz listesi) AuthorizeTerminal middleware'inde
            // yapılır; böylece route:cache sonrası da doğru çalışır.
            if ($this->enabled($config)) {
                $this->loadViewsFrom(__DIR__ . '/../resources/views', 'terminal');
                $this->handleRoutes($this->app['router'], $config);
            }

            if ($this->app->runningInConsole() === true) {
                $this->handlePublishes();
            }
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                logger()->error('Terminal Service Provider Boot Error: ' . $e->getMessage());
            }

            // Hatayı sessizce yutmak teşhisi imkânsızlaştırıyordu; geliştirme
            // ortamında görünür olsun.
            if (method_exists($this->app, 'hasDebugModeEnabled') && $this->app->hasDebugModeEnabled()) {
                throw $e;
            }
        }
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/terminal.php', 'terminal');

        $this->app->bind(Application::class, function ($app) {
            $config = $app['config']['terminal'] ?? [];
            $artisan = new Application($app, $app['events'], $app->version());
            return $artisan->resolveCommands($config['commands'] ?? []);
        });

        $this->app->bind(Kernel::class, function ($app) {
            $config = $app['config']['terminal'] ?? [];

            // Build endpoint URL safely
            $endpoint = '';
            try {
                $routeName = Arr::get($config, 'route.as', 'terminal.') . 'endpoint';
                $endpoint = route($routeName);
            } catch (Exception $e) {
                // Route might not be available yet, use fallback
                $prefix = Arr::get($config, 'route.prefix', 'terminal');
                $endpoint = url($prefix . '/endpoint');
            }

            return new Kernel($app[Application::class], array_merge($config, [
                'basePath' => $app->basePath(),
                'environment' => $app->environment(),
                'version' => $app->version(),
                'endpoint' => $endpoint,
            ]));
        });
    }

    /**
     * register routes.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @param  array  $config
     */
    protected function handleRoutes(Router $router, $config = [])
    {
        // Laravel 12 compatible route caching check
        if (!$this->routesCached()) {
            $routeConfig = Arr::get($config, 'route', []);

            // Erişim doğrulaması kullanıcı middleware'lerinden önce çalışsın
            $routeConfig['middleware'] = array_merge(
                [AuthorizeTerminal::class],
                (array) Arr::get($routeConfig, 'middleware', [])
            );

            $router->group($routeConfig, function () {
                require __DIR__ . '/../routes/web.php';
            });
        }
    }

    /**
     * Laravel 12 compatible route caching check
     *
     * @return bool
     */
    protected function routesCached()
    {
        try {
            // Try the standard Laravel method first
            if (method_exists($this->app, 'routesAreCached')) {
                return $this->app->routesAreCached();
            }

            // Fallback: check if routes cache file exists
            if (method_exists($this->app, 'getCachedRoutesPath')) {
                $routeCachePath = $this->app->getCachedRoutesPath();
                return file_exists($routeCachePath);
            }

            // Ultimate fallback: assume routes are not cached
            return false;
        } catch (Exception $e) {
            // If anything fails, assume routes are not cached
            return false;
        }
    }

    /**
     * handle publishes.
     */
    protected function handlePublishes()
    {
        $this->publishes([
            __DIR__ . '/../config/terminal.php' => config_path('terminal.php'),
            __DIR__ . '/../resources/views' => base_path('resources/views/vendor/terminal'),
            __DIR__ . '/../public' => public_path('vendor/terminal'),
        ], 'terminal');
    }

    /**
     * Terminal etkin mi?
     *
     * Önceden bu kontrol 'enabled' değerini === ile karşılaştırdığı ve
     * config her zaman bool döndürdüğü için IP beyaz listesi dalı hiç
     * çalışmıyordu. IP kontrolü artık AuthorizeTerminal middleware'inde.
     *
     * @param  array  $config
     * @return bool
     */
    private function enabled($config)
    {
        return filter_var(Arr::get($config, 'enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}
