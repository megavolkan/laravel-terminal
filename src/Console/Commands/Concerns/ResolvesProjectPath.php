<?php

namespace Recca0120\Terminal\Console\Commands\Concerns;

/**
 * Kullanıcıdan gelen yolları proje köküne sabitler.
 *
 * realpath() kullanılmaz: hem test ortamındaki vfs:// akışlarıyla hem de
 * henüz var olmayan dosyalarla (vi ile yeni dosya yazma) çalışması gerekir.
 * Bu yüzden normalleştirme tamamen sözdizimseldir.
 */
trait ResolvesProjectPath
{
    /**
     * Proje kökünü döndürür.
     */
    protected function projectRoot(): string
    {
        return function_exists('base_path') ? base_path() : (string) getcwd();
    }

    /**
     * Kullanıcı yolunu proje köküne göre çözer.
     * Kök dizinin dışına çıkan yollarda null döner.
     */
    protected function resolveProjectPath(string $path, ?string $root = null): ?string
    {
        $root = $this->normalizePath($root ?? $this->projectRoot());

        $combined = $path === ''
            ? $root
            : rtrim($root, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');

        $resolved = $this->normalizePath($combined);

        return $this->isWithin($resolved, $root) ? $resolved : null;
    }

    /**
     * Çözülen yol kök dizinin içinde mi?
     */
    protected function isWithin(string $path, string $root): bool
    {
        $root = rtrim($root, '/');

        return $path === $root || str_starts_with($path, $root . '/');
    }

    /**
     * "." ve ".." parçalarını dosya sistemine dokunmadan çözer.
     * vfs:// gibi akış sarmalayıcılarını ve mutlak/göreli yolları korur.
     */
    protected function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*://)#', $path, $matches) === 1) {
            $prefix = $matches[1];
            $path = substr($path, strlen($prefix));
        } elseif (preg_match('#^([a-zA-Z]:/)#', $path, $matches) === 1) {
            $prefix = $matches[1];
            $path = substr($path, strlen($prefix));
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . implode('/', $segments);
    }

    /**
     * Yol kök dizin dışına çıktığında standart hata mesajını yazar.
     */
    protected function outsideProjectError(string $path): void
    {
        $this->error('Erişim reddedildi: "' . $path . '" proje dizininin dışında.');
    }
}
