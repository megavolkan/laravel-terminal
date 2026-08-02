<?php

namespace Recca0120\Terminal\Console\Commands;

use Composer\Console\Application as ComposerApplication;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Recca0120\Terminal\Contracts\TerminalCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Composer extends Command implements TerminalCommand
{
    // The terminal JS always sends args as --command="..." when commandLine=true.
    protected $signature = 'composer {--command= : Çalıştırılacak composer komutu}';

    protected $description = 'Composer komutlarını çalıştırır (shell gerektirmez)';

    /**
     * Shell erişimi veya etkileşim gerektirdiği için web terminalde
     * çalıştırılmasına izin verilmeyen komutlar.
     */
    protected const BLOCKED_COMMANDS = [
        'browse', 'create-project', 'exec', 'global', 'home',
        'run', 'run-script', 'self-update', 'selfupdate',
    ];

    /**
     * vendor klasörünü / autoload dosyalarını değiştiren komutlar.
     * Bunlar --no-scripts ile çalışır ve ardından package:discover
     * aynı süreç içinde tetiklenir.
     */
    protected const VENDOR_COMMANDS = [
        'install', 'update', 'upgrade', 'require', 'remove',
        'dump-autoload', 'dumpautoload',
    ];

    public function handle(): int
    {
        $commandLine = trim($this->option('command') ?? '');

        if ($commandLine === '') {
            $this->showHelp();

            return 0;
        }

        $parts = array_values(array_filter(explode(' ', $commandLine)));
        $name = $parts[0] ?? '';

        switch ($name) {
            case 'show':
            case 'info':
                return $this->cmdShow($parts);

            case 'outdated':
                return $this->cmdOutdated($parts);
        }

        if (in_array($name, self::BLOCKED_COMMANDS, true)) {
            $this->error('"composer ' . $name . '" web terminalde desteklenmiyor (shell erişimi veya etkileşim gerektirir).');

            return 1;
        }

        return $this->runEmbeddedComposer($name, $commandLine);
    }

    // -------------------------------------------------------------------------
    // Gömülü Composer (shell fonksiyonları olmadan, aynı PHP süreci içinde)
    // -------------------------------------------------------------------------

    protected function runEmbeddedComposer(string $name, string $commandLine): int
    {
        if (!class_exists(ComposerApplication::class)) {
            $this->error('composer/composer paketi yüklü değil.');
            $this->line('Geliştirme ortamında <fg=yellow>composer require composer/composer</fg=yellow> çalıştırıp');
            $this->line('vendor klasörünü sunucuya yükledikten sonra bu komut kullanılabilir.');

            return 1;
        }

        if (in_array($name, ['require', 'remove', 'update', 'upgrade'], true)
            && !is_writable($this->projectPath('composer.json'))) {
            $this->error('composer.json yazılabilir değil. Dosya izinlerini kontrol edin.');

            return 1;
        }

        $this->prepareEnvironment($name);

        $changesVendor = in_array($name, self::VENDOR_COMMANDS, true);

        // post-autoload-dump içindeki "@php artisan package:discover" bir alt
        // süreç başlatmak ister (proc_open); --no-scripts ile atlanır ve
        // package:discover aşağıda aynı süreç içinde çalıştırılır.
        $flags = ' --no-interaction';
        if ($changesVendor) {
            $flags .= ' --no-scripts';
        }
        if (in_array($name, ['install', 'update', 'upgrade', 'require'], true)) {
            $flags .= ' --prefer-dist';
        }
        if (in_array($name, ['install', 'update', 'upgrade', 'require', 'remove'], true)) {
            $flags .= ' --no-progress';
        }

        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter(true));
        $cwd = getcwd();
        $previousErrorHandler = $this->currentErrorHandler();

        try {
            chdir($this->projectPath());

            $application = new ComposerApplication();
            $application->setAutoExit(false);
            $application->setCatchExceptions(true);

            $exitCode = $application->run(new StringInput($commandLine . $flags), $output);
        } catch (\Throwable $e) {
            $this->writeRaw($output->fetch());
            $this->error('Composer hatası: ' . $e->getMessage());

            return 1;
        } finally {
            // Composer doRun() içinde kendi error handler'ını kaydeder;
            // önceki handler'a (Laravel) geri dön.
            $this->restoreErrorHandler($previousErrorHandler);

            if ($cwd !== false) {
                chdir($cwd);
            }
        }

        $this->writeRaw($output->fetch());

        if ($exitCode === 0 && $changesVendor) {
            $this->runPackageDiscover();

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
        }

        return $exitCode;
    }

    protected function prepareEnvironment(string $name): void
    {
        @ini_set('memory_limit', '-1');

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $home = $this->storagePath('app/composer-home');
        if (!is_dir($home)) {
            @mkdir($home, 0755, true);
        }

        putenv('COMPOSER_HOME=' . $home);
        putenv('COMPOSER_CACHE_DIR=' . $home . DIRECTORY_SEPARATOR . 'cache');
        putenv('COMPOSER_NO_INTERACTION=1');
        putenv('COMPOSER_ALLOW_SUPERUSER=1');

        $limit = (string) ini_get('memory_limit');
        if (in_array($name, ['update', 'upgrade', 'require'], true)
            && $limit !== '-1'
            && $this->toBytes($limit) < 512 * 1024 * 1024) {
            $this->warn('Uyarı: memory_limit ' . $limit . ' — bağımlılık çözümü sırasında bellek yetersiz kalabilir.');
        }
    }

    /**
     * package:discover'ı alt süreç açmadan, mevcut uygulama içinde çalıştırır.
     */
    protected function runPackageDiscover(): void
    {
        try {
            $kernel = $this->laravel->make(ConsoleKernel::class);
            $kernel->call('package:discover');
            $this->writeRaw($kernel->output());
        } catch (\Throwable $e) {
            $this->warn('package:discover çalıştırılamadı: ' . $e->getMessage());
            $this->line('Gerekirse <fg=yellow>artisan package:discover</fg=yellow> komutunu elle çalıştırın.');
        }
    }

    /**
     * Composer çıktısı zaten ANSI kodları içerir; Symfony formatter'dan
     * tekrar geçirmeden olduğu gibi yazar.
     */
    protected function writeRaw(string $text): void
    {
        $text = rtrim($text);

        if ($text !== '') {
            $this->output->write($text, true, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Aktif error handler'ı, handler yığınını değiştirmeden döndürür.
     */
    protected function currentErrorHandler(): mixed
    {
        $handler = set_error_handler(static fn () => false);
        restore_error_handler();

        return $handler;
    }

    /**
     * Composer'ın kaydettiği error handler'ları, çalıştırma öncesindeki
     * handler tekrar aktif olana kadar geri alır.
     */
    protected function restoreErrorHandler(mixed $previous): void
    {
        for ($i = 0; $i < 10; $i++) {
            if ($this->currentErrorHandler() === $previous) {
                return;
            }

            restore_error_handler();
        }
    }

    protected function projectPath(string $path = ''): string
    {
        $base = function_exists('base_path') ? base_path() : (string) getcwd();

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
    }

    protected function storagePath(string $path = ''): string
    {
        $base = function_exists('storage_path') ? storage_path() : sys_get_temp_dir();

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * "128M" gibi ini değerlerini byte'a çevirir.
     */
    protected function toBytes(string $limit): float
    {
        $limit = trim($limit);

        if ($limit === '' || $limit === '-1') {
            return PHP_FLOAT_MAX;
        }

        $value = (float) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    // -------------------------------------------------------------------------
    // composer show (natif — installed.json üzerinden, ağ gerektirmez)
    // -------------------------------------------------------------------------

    protected function cmdShow(array $parts): int
    {
        $packages = $this->readInstalledPackages();

        if ($packages === null) {
            $this->error('vendor/composer/installed.json okunamadı.');

            return 1;
        }

        // Paket adına göre filtrele: composer show vendor/package
        $filter = $parts[1] ?? null;
        if ($filter) {
            $packages = array_filter($packages, fn ($p) => str_contains($p['name'], $filter));
        }

        if (empty($packages)) {
            $this->line('Paket bulunamadı.');

            return 0;
        }

        $this->line(sprintf('<fg=green>%-45s %-15s %s</fg=green>', 'Paket', 'Sürüm', 'Açıklama'));
        $this->line(str_repeat('-', 90));

        foreach ($packages as $pkg) {
            $this->line(sprintf(
                '%-45s <fg=yellow>%-15s</fg=yellow> %s',
                $pkg['name'],
                $pkg['version'] ?? '?',
                substr($pkg['description'] ?? '', 0, 50)
            ));
        }

        $this->line('');
        $this->line('<fg=green>' . count($packages) . ' paket</fg=green>');

        return 0;
    }

    // -------------------------------------------------------------------------
    // composer outdated (natif — Packagist API üzerinden)
    // -------------------------------------------------------------------------

    protected function cmdOutdated(array $parts): int
    {
        $packages = $this->readInstalledPackages();

        if ($packages === null) {
            $this->error('vendor/composer/installed.json okunamadı.');

            return 1;
        }

        $this->line('<fg=yellow>Packagist üzerinden güncel sürümler denetleniyor...</fg=yellow>');
        $this->line('');

        $outdated = [];

        foreach ($packages as $pkg) {
            $name    = $pkg['name'] ?? null;
            $current = $pkg['version'] ?? null;

            if (!$name || !$current || str_starts_with($current, 'dev-')) {
                continue;
            }

            $latest = $this->fetchLatestVersion($name);

            if ($latest && $latest !== $current && version_compare(
                ltrim($latest, 'v'),
                ltrim($current, 'v'),
                '>'
            )) {
                $outdated[] = [
                    'name'    => $name,
                    'current' => $current,
                    'latest'  => $latest,
                ];
            }
        }

        if (empty($outdated)) {
            $this->line('<fg=green>Tüm paketler güncel.</fg=green>');

            return 0;
        }

        $this->line(sprintf('<fg=green>%-45s %-15s %s</fg=green>', 'Paket', 'Mevcut', 'Güncel'));
        $this->line(str_repeat('-', 80));

        foreach ($outdated as $pkg) {
            $this->line(sprintf(
                '%-45s <fg=yellow>%-15s</fg=yellow> <fg=green>%s</fg=green>',
                $pkg['name'],
                $pkg['current'],
                $pkg['latest']
            ));
        }

        $this->line('');
        $this->line('<fg=yellow>' . count($outdated) . ' paket güncel değil</fg=yellow>');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Yardımcılar
    // -------------------------------------------------------------------------

    /**
     * vendor/composer/installed.json içindeki kurulu paketleri okur.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function readInstalledPackages(): ?array
    {
        $file = $this->projectPath('vendor/composer/installed.json');

        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        if (!is_array($data)) {
            return null;
        }

        // Composer 2 paketleri 'packages' anahtarı altında tutar
        $packages = $data['packages'] ?? $data;

        usort($packages, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return $packages;
    }

    /**
     * Packagist'ten paketin en son kararlı sürümünü sorgular.
     * file_get_contents ile HTTP kullanır (shell fonksiyonu gerekmez).
     */
    protected function fetchLatestVersion(string $package): ?string
    {
        // Packagist v2 metadata endpoint'i
        $url = 'https://repo.packagist.org/p2/' . $package . '.json';

        $context = stream_context_create([
            'http' => [
                'timeout'        => 5,
                'ignore_errors'  => true,
                'user_agent'     => 'Laravel-Terminal/1.0',
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if (!$body) {
            return null;
        }

        $data = json_decode($body, true);

        if (!isset($data['packages'][$package])) {
            return null;
        }

        // v2 endpoint'i sürümleri azalan sırada döner; ilk kararlı sürümü al
        foreach ($data['packages'][$package] as $release) {
            $version = $release['version'] ?? null;
            if ($version && !str_contains($version, 'dev') && !str_contains($version, 'alpha') && !str_contains($version, 'beta') && !str_contains($version, 'RC')) {
                return $version;
            }
        }

        return null;
    }

    protected function showHelp(): void
    {
        $this->line('<fg=cyan>Composer Web Terminal</fg=cyan> (shell gerektirmez)');
        $this->line('');
        $this->line('<fg=green>Paket bilgisi:</fg=green>');
        $this->line('  <fg=yellow>composer show [paket]</fg=yellow>       Kurulu paketleri listele');
        $this->line('  <fg=yellow>composer outdated</fg=yellow>           Güncellemeleri denetle (Packagist)');
        $this->line('');
        $this->line('<fg=green>Bağımlılık yönetimi (gömülü Composer):</fg=green>');
        $this->line('  <fg=yellow>composer install</fg=yellow>            composer.lock\'a göre paketleri kur');
        $this->line('  <fg=yellow>composer require vendor/paket</fg=yellow>  Paket ekle');
        $this->line('  <fg=yellow>composer remove vendor/paket</fg=yellow>   Paket kaldır');
        $this->line('  <fg=yellow>composer update [vendor/paket]</fg=yellow> Paketleri güncelle');
        $this->line('  <fg=yellow>composer dump-autoload</fg=yellow>      Autoload dosyalarını yeniden üret');
        $this->line('  <fg=yellow>composer clear-cache</fg=yellow>        Composer önbelleğini temizle');
        $this->line('');
        $this->line('<fg=green>Notlar:</fg=green>');
        $this->line('  - Komutlar --no-scripts ile çalışır; package:discover otomatik tetiklenir.');
        $this->line('  - Paketler --prefer-dist ile (zip) kurulur; git kaynaklı dev-* paketler desteklenmez.');
        $this->line('  - Kapsamlı "update" işlemleri hostun bellek/süre limitine takılabilir.');
    }
}
