<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Console\Command;
use Recca0120\Terminal\Contracts\TerminalCommand;

class Composer extends Command implements TerminalCommand
{
    protected $signature = 'composer {cmd* : Composer command to run (e.g. outdated, show, install)}';

    protected $description = 'Run Composer commands via composer.phar';

    public function handle(): int
    {
        $cmdParts = array_values(array_filter($this->argument('cmd')));

        if (empty($cmdParts)) {
            $this->showHelp();
            return 0;
        }

        $safeParts = array_values(array_filter(array_map([$this, 'sanitizePart'], $cmdParts)));

        if (empty($safeParts)) {
            $this->error('Invalid command characters.');
            return 1;
        }

        $composerPhar = $this->findComposerPhar();

        if ($composerPhar === null) {
            $this->line('<fg=red>❌ composer.phar not found.</fg=red>');
            $this->line('Upload to: <fg=yellow>' . base_path() . '</fg=yellow>');
            $this->line('Download: <fg=blue>https://getcomposer.org/composer.phar</fg=blue>');
            return 1;
        }

        $displayCmd = implode(' ', $safeParts);
        $this->line('<fg=yellow>⏳ Running: composer ' . $displayCmd . '</fg=yellow>');
        $this->line('');

        $output = $this->runViaPhar($safeParts, $composerPhar);

        if ($output === null) {
            $this->error('Failed to load composer.phar. Make sure it is a valid phar archive.');
            return 1;
        }

        foreach (explode("\n", rtrim($output)) as $line) {
            $this->line($line);
        }

        $this->line('');
        $this->line('<fg=green>✅ Done</fg=green>');

        return 0;
    }

    /**
     * Run a composer command by loading composer.phar directly into the PHP
     * process via the phar:// stream wrapper — no shell functions needed.
     *
     * @param  string[]  $cmdParts  e.g. ['outdated'] or ['update', 'vendor/pkg']
     */
    protected function runViaPhar(array $cmdParts, string $pharPath): ?string
    {
        $autoload = 'phar://' . $pharPath . '/vendor/autoload.php';

        if (!file_exists($autoload)) {
            return null;
        }

        try {
            // Load Composer's own autoloader from inside the phar archive.
            // PHP's phar:// stream wrapper is a core feature — no exec needed.
            require_once $autoload;

            /** @var \Composer\Console\Application $app */
            $app = new \Composer\Console\Application();
            $app->setAutoExit(false);

            // Build argv: ['composer', 'outdated']  or  ['composer', 'update', 'vendor/pkg']
            $argv = array_merge(['composer'], $cmdParts);
            $input = new \Symfony\Component\Console\Input\ArgvInput($argv);

            $output = new \Symfony\Component\Console\Output\BufferedOutput(
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL,
                false // no ANSI
            );

            if (!getenv('COMPOSER_HOME')) {
                putenv('COMPOSER_HOME=' . sys_get_temp_dir() . '/.composer');
            }

            $cwd = getcwd();
            chdir(base_path());

            try {
                $app->run($input, $output);
            } finally {
                chdir($cwd);
            }

            return $output->fetch();

        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Find composer.phar in common locations.
     */
    protected function findComposerPhar(): ?string
    {
        $locations = [
            base_path('composer.phar'),
            dirname(base_path()) . '/composer.phar',
        ];

        foreach ($locations as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Allow only safe characters in each command part.
     * escapeshellarg/escapeshellcmd are disabled on shared hosting.
     */
    protected function sanitizePart(string $part): string
    {
        return trim(preg_replace('/[^a-zA-Z0-9\-:\/\.\=\^\~\@\_]/', '', $part));
    }

    protected function showHelp(): void
    {
        $this->line('<fg=cyan>Composer Terminal</fg=cyan>');
        $this->line('');
        $this->line('Requires <fg=yellow>composer.phar</fg=yellow> in: ' . base_path());
        $this->line('');
        $this->line('<fg=green>Usage:</fg=green>  composer <command> [arguments]');
        $this->line('');
        $this->line('<fg=blue>Examples:</fg=blue>');
        $this->line('  composer show');
        $this->line('  composer outdated');
        $this->line('  composer install');
        $this->line('  composer update vendor/package');
        $this->line('  composer require vendor/package');
        $this->line('  composer dump-autoload');
    }
}
