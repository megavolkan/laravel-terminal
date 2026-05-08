<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Console\Command;
use Recca0120\Terminal\Contracts\TerminalCommand;

class Composer extends Command implements TerminalCommand
{
    // The terminal JS always sends args as --command="..." when commandLine=true.
    protected $signature = 'composer {--command= : Composer command to run}';

    protected $description = 'Run Composer commands';

    public function handle(): int
    {
        $command = trim($this->option('command') ?? '');

        if (empty($command)) {
            $this->showHelp();
            return 0;
        }

        $parts = array_values(array_filter(explode(' ', $command)));

        switch ($parts[0] ?? '') {
            case 'show':
            case 'info':
                return $this->cmdShow($parts);

            case 'outdated':
                return $this->cmdOutdated($parts);

            case 'install':
            case 'update':
            case 'require':
            case 'remove':
            case 'dump-autoload':
            case 'clear-cache':
                $this->showShellNotAvailable($parts[0]);
                return 1;

            default:
                $this->error('Unknown command: ' . $parts[0]);
                $this->showHelp();
                return 1;
        }
    }

    // -------------------------------------------------------------------------
    // composer show
    // -------------------------------------------------------------------------

    protected function cmdShow(array $parts): int
    {
        $packages = $this->readInstalledPackages();

        if ($packages === null) {
            $this->error('Cannot read vendor/composer/installed.json');
            return 1;
        }

        // Filter by package name if given: composer show vendor/package
        $filter = $parts[1] ?? null;
        if ($filter) {
            $packages = array_filter($packages, fn ($p) => str_contains($p['name'], $filter));
        }

        if (empty($packages)) {
            $this->line('No packages found.');
            return 0;
        }

        $this->line(sprintf('<fg=green>%-45s %-15s %s</fg=green>', 'Package', 'Version', 'Description'));
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
        $this->line('<fg=green>' . count($packages) . ' packages</fg=green>');

        return 0;
    }

    // -------------------------------------------------------------------------
    // composer outdated
    // -------------------------------------------------------------------------

    protected function cmdOutdated(array $parts): int
    {
        $packages = $this->readInstalledPackages();

        if ($packages === null) {
            $this->error('Cannot read vendor/composer/installed.json');
            return 1;
        }

        $this->line('<fg=yellow>Checking Packagist for latest versions...</fg=yellow>');
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
            $this->line('<fg=green>All packages are up to date.</fg=green>');
            return 0;
        }

        $this->line(sprintf('<fg=green>%-45s %-15s %s</fg=green>', 'Package', 'Current', 'Latest'));
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
        $this->line('<fg=yellow>' . count($outdated) . ' package(s) outdated</fg=yellow>');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Read all installed packages from vendor/composer/installed.json.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function readInstalledPackages(): ?array
    {
        $file = base_path('vendor/composer/installed.json');

        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        if (!is_array($data)) {
            return null;
        }

        // Composer 2 wraps packages under a 'packages' key
        $packages = $data['packages'] ?? $data;

        usort($packages, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return $packages;
    }

    /**
     * Query Packagist for the latest stable version of a package.
     * Uses file_get_contents with HTTP (no shell functions needed).
     */
    protected function fetchLatestVersion(string $package): ?string
    {
        // Packagist's v2 metadata endpoint
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

        // The v2 endpoint returns versions in descending order; pick the first stable one
        foreach ($data['packages'][$package] as $release) {
            $version = $release['version'] ?? null;
            if ($version && !str_contains($version, 'dev') && !str_contains($version, 'alpha') && !str_contains($version, 'beta') && !str_contains($version, 'RC')) {
                return $version;
            }
        }

        return null;
    }

    protected function showShellNotAvailable(string $cmd): void
    {
        $this->line('<fg=red>❌ ' . $cmd . ' requires shell access.</fg=red>');
        $this->line('');
        $this->line('All shell execution functions (exec, popen, proc_open...) are');
        $this->line('disabled on this server. Run this command locally instead:');
        $this->line('');
        $this->line('  <fg=yellow>composer ' . $cmd . '</fg=yellow>');
        $this->line('');
        $this->line('Then upload the changed vendor files via FTP.');
    }

    protected function showHelp(): void
    {
        $this->line('<fg=cyan>Composer Terminal</fg=cyan>');
        $this->line('');
        $this->line('<fg=green>Supported commands (no shell required):</fg=green>');
        $this->line('  <fg=yellow>composer show</fg=yellow>               List installed packages');
        $this->line('  <fg=yellow>composer show vendor/package</fg=yellow>  Show a specific package');
        $this->line('  <fg=yellow>composer outdated</fg=yellow>           Check for updates (queries Packagist)');
        $this->line('');
        $this->line('<fg=red>Requires shell (run locally + FTP vendor):</fg=red>');
        $this->line('  composer install / update / require / remove');
    }
}
