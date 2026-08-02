<?php

namespace Recca0120\Terminal\Http\Middleware;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Terminale erişimi her istekte doğrular.
 *
 * Kontrol route kaydı sırasında değil burada yapılır; aksi halde route
 * önbelleğe alındığında (route:cache) o anki isteğin IP'sine göre verilen
 * karar tüm isteklere uygulanırdı.
 */
class AuthorizeTerminal
{
    public function handle(Request $request, Closure $next)
    {
        $config = $this->config();

        if ($this->enabled($config) === false || $this->allowedIp($request, $config) === false) {
            // Terminalin varlığını sızdırmamak için 403 yerine 404
            throw new NotFoundHttpException();
        }

        return $next($request);
    }

    /**
     * Config, config() helper'ı yerine container üzerinden okunur; helper
     * illuminate/foundation'dan gelir ve paket bağlamında bulunmayabilir.
     *
     * @return array<string, mixed>
     */
    protected function config(): array
    {
        try {
            $container = Container::getInstance();

            if ($container->bound('config') === false) {
                return [];
            }

            $config = $container->make('config')->get('terminal');
        } catch (Throwable $e) {
            return [];
        }

        return is_array($config) ? $config : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function enabled(array $config): bool
    {
        return filter_var(Arr::get($config, 'enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Beyaz liste boşsa IP kontrolü yapılmaz; erişim yalnızca route
     * middleware'lerine (örn. auth) bağlı kalır.
     *
     * @param  array<string, mixed>  $config
     */
    protected function allowedIp(Request $request, array $config): bool
    {
        $whitelists = Arr::get($config, 'whitelists', []);

        if (is_array($whitelists) === false || $whitelists === []) {
            return true;
        }

        return in_array($request->getClientIp(), $whitelists, true);
    }
}
