<?php

namespace Recca0120\Terminal\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Recca0120\Terminal\Contracts\TerminalCommand;

class Composer extends Command implements TerminalCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'composer {--command= : The composer command to execute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Composer commands';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $command = trim($this->option('command') ?? '');

        if (empty($command)) {
            $this->showHelp();
            return 0;
        }

        try {
            $this->executeComposerCommand($command);
        } catch (Exception $e) {
            $this->error('Composer Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Sanitize a composer command string — allow only safe characters.
     * Never use escapeshellarg/escapeshellcmd (disabled on shared hosting).
     */
    protected function sanitizeCommand(string $command): string
    {
        // Allow: letters, digits, spaces, hyphens, colons, slashes, dots, equals, carets, tildes, @
        // Block: semicolons, pipes, backticks, $, >, <, &, (, ), {, }, newlines, null bytes
        return preg_replace('/[^a-zA-Z0-9 \-:\/\.\=\^\~\@\_\*]/', '', $command);
    }

    /**
     * Find an executable PHP CLI binary on the server.
     */
    protected function findPhpBinary(): string
    {
        $candidates = [
            PHP_BINARY,
            '/usr/local/bin/php',
            '/usr/bin/php',
            '/opt/cpanel/ea-php83/root/usr/bin/php',
            '/opt/cpanel/ea-php82/root/usr/bin/php',
            '/opt/cpanel/ea-php81/root/usr/bin/php',
        ];

        foreach ($candidates as $path) {
            if (is_executable($path) && strpos($path, 'fpm') === false) {
                return $path;
            }
        }

        // Fallback: current binary even if FPM
        return PHP_BINARY;
    }

    /**
     * Find composer.phar in common locations.
     */
    protected function findComposerPhar(): ?string
    {
        $locations = [
            base_path('composer.phar'),
            base_path('../composer.phar'),
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/opt/homebrew/bin/composer',
        ];

        foreach ($locations as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Execute the composer command.
     * Tries multiple strategies: popen → passthru → proc_open → exec → shell_exec.
     */
    protected function executeComposerCommand(string $command): void
    {
        $composerPath = $this->findComposerPhar();

        if (!$composerPath) {
            $this->line('<fg=red>❌ composer.phar not found.</fg=red>');
            $this->line('Upload composer.phar to: <fg=yellow>' . base_path() . '</fg=yellow>');
            $this->line('Download from: https://getcomposer.org/composer.phar');
            return;
        }

        $phpBin  = $this->findPhpBinary();
        $safeCmd = $this->sanitizeCommand($command);

        if (empty($safeCmd)) {
            $this->error('Invalid command characters.');
            return;
        }

        $fullCommand = $phpBin . ' ' . $composerPath . ' ' . $safeCmd . ' --no-ansi 2>&1';

        $this->line('<fg=yellow>⏳ Running composer ' . $safeCmd . '...</fg=yellow>');
        $this->line('');

        $oldDir = getcwd();
        chdir(base_path());

        try {
            $output = $this->runCommand($fullCommand);

            if ($output === null) {
                $this->error('No shell execution method available on this server.');
                $this->line('Disabled: exec, shell_exec, proc_open, popen, passthru, system');
            } else {
                foreach (explode("\n", rtrim($output)) as $line) {
                    $this->line($line);
                }
                $this->line('');
                $this->line('<fg=green>✅ Done</fg=green>');
            }
        } finally {
            chdir($oldDir);
        }
    }

    /**
     * Try every available shell execution method in order of preference.
     * Returns output string, or null if nothing is available.
     */
    protected function runCommand(string $command): ?string
    {
        $disabled = array_map('trim', explode(',', ini_get('disable_functions')));

        // Strategy 1: popen (streaming, works on most shared hosts)
        if (!in_array('popen', $disabled) && function_exists('popen')) {
            $output = '';
            $handle = popen($command, 'r');
            if (is_resource($handle)) {
                while (!feof($handle)) {
                    $output .= fgets($handle, 4096);
                }
                pclose($handle);
                return $output;
            }
        }

        // Strategy 2: passthru (output buffering)
        if (!in_array('passthru', $disabled) && function_exists('passthru')) {
            ob_start();
            passthru($command);
            return ob_get_clean();
        }

        // Strategy 3: system
        if (!in_array('system', $disabled) && function_exists('system')) {
            ob_start();
            system($command);
            return ob_get_clean();
        }

        // Strategy 4: proc_open
        if (!in_array('proc_open', $disabled) && function_exists('proc_open')) {
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $pipes = [];
            $process = proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return $output;
            }
        }

        // Strategy 5: exec
        if (!in_array('exec', $disabled) && function_exists('exec')) {
            $outputLines = [];
            exec($command, $outputLines);
            return implode("\n", $outputLines);
        }

        // Strategy 6: shell_exec
        if (!in_array('shell_exec', $disabled) && function_exists('shell_exec')) {
            return shell_exec($command) ?? '';
        }

        return null;
    }

    /**
     * Show available commands help.
     */
    protected function showHelp(): void
    {
        $this->line('<fg=cyan>Composer Terminal</fg=cyan>');
        $this->line('');
        $this->line('<fg=green>Usage:</fg=green>  composer --command="<command>"');
        $this->line('');
        $this->line('<fg=blue>Examples:</fg=blue>');
        $this->line('  composer --command="show"');
        $this->line('  composer --command="outdated"');
        $this->line('  composer --command="install"');
        $this->line('  composer --command="update"');
        $this->line('  composer --command="require vendor/package"');
        $this->line('  composer --command="dump-autoload"');
        $this->line('');
        $this->line('<fg=yellow>Note:</fg=yellow> Requires composer.phar in project root.');
    }
}
