<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Console\Command;
use Recca0120\Terminal\Contracts\TerminalCommand;

class Composer extends Command implements TerminalCommand
{
    protected $signature = 'composer {--command= : The composer command to execute}';

    protected $description = 'Run Composer commands via composer.phar';

    public function handle(): int
    {
        $command = trim($this->option('command') ?? '');

        if (empty($command)) {
            $this->showHelp();
            return 0;
        }

        $safeCmd = $this->sanitizeCommand($command);

        if (empty($safeCmd)) {
            $this->error('Invalid command characters.');
            return 1;
        }

        $composerPhar = $this->findComposerPhar();

        if ($composerPhar === null) {
            $this->line('<fg=red>❌ composer.phar not found.</fg=red>');
            $this->line('Upload composer.phar to: <fg=yellow>' . base_path() . '</fg=yellow>');
            $this->line('Download from: <fg=blue>https://getcomposer.org/composer.phar</fg=blue>');
            return 1;
        }

        $this->line('<fg=yellow>⏳ Running: composer ' . $safeCmd . '</fg=yellow>');
        $this->line('');

        $output = $this->runViaPhar($safeCmd, $composerPhar);

        if ($output === null) {
            $this->error('Failed to run composer. Check that composer.phar is a valid phar archive.');
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
     */
    protected function runViaPhar(string $command, string $pharPath): ?string
    {
        $autoload = 'phar://' . $pharPath . '/vendor/autoload.php';

        if (!file_exists($autoload)) {
            return null;
        }

        try {
            // Load Composer's own autoloader from inside the phar archive.
            // PHP's phar:// stream wrapper is a core feature — no exec needed.
            require_once $autoload;

            // Prevent Composer from calling exit() so the web request continues.
            /** @var \Composer\Console\Application $app */
            $app = new \Composer\Console\Application();
            $app->setAutoExit(false);

            // Build argv for Composer.
            $parts = array_values(array_filter(explode(' ', $command)));
            array_unshift($parts, 'composer');

            $input = new \Symfony\Component\Console\Input\ArgvInput($parts);

            // Capture all output — no ANSI codes.
            $output = new \Symfony\Component\Console\Output\BufferedOutput(
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL,
                false
            );

            // Ensure Composer writes to a writable home directory.
            if (!getenv('COMPOSER_HOME')) {
                putenv('COMPOSER_HOME=' . sys_get_temp_dir() . '/.composer');
            }

            // Set working directory to project root.
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
     * Allow only safe characters in the composer command string.
     * escapeshellarg/escapeshellcmd are disabled on shared hosting.
     */
    protected function sanitizeCommand(string $command): string
    {
        return trim(preg_replace('/[^a-zA-Z0-9 \-:\/\.\=\^\~\@\_]/', '', $command));
    }

    protected function showHelp(): void
    {
        $this->line('<fg=cyan>Composer Terminal</fg=cyan>');
        $this->line('');
        $this->line('Requires <fg=yellow>composer.phar</fg=yellow> in: ' . base_path());
        $this->line('');
        $this->line('<fg=green>Examples:</fg=green>');
        $this->line('  composer --command="show"');
        $this->line('  composer --command="outdated"');
        $this->line('  composer --command="install"');
        $this->line('  composer --command="update vendor/package"');
        $this->line('  composer --command="require vendor/package"');
        $this->line('  composer --command="dump-autoload"');
    }
}
