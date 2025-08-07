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
    protected $description = 'Run Composer commands - Full Access';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $command = trim($this->option('command') ?? '');

        // If no command provided, show help
        if (empty($command)) {
            $this->showHelp();
            return 0;
        }

        // Execute any composer command
        try {
            $this->executeComposerCommand($command);
        } catch (Exception $e) {
            $this->error('Composer Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Execute Composer command with full access
     *
     * @param string $command
     * @throws Exception
     */
    protected function executeComposerCommand($command)
    {
        // Find composer executable
        $composerPath = $this->findComposerOnSharedHost();

        if (!$composerPath) {
            $this->showComposerNotFoundHelp();
            return;
        }

        // Set working directory to Laravel root
        $workingDir = base_path();

        // Build the command - properly escaped for paths with spaces
        // Note: $composerPath is already escaped in findComposerOnSharedHost()
        $fullCommand = $composerPath . ' ' . escapeshellcmd($command) . ' --no-ansi 2>&1';

        $this->line('<fg=blue>Using:</fg=blue> ' . str_replace(['"', "'"], '', $composerPath));
        $this->line('<fg=blue>Executing:</fg=blue> ' . $command);
        $this->line('<fg=yellow>Working Directory:</fg=yellow> ' . $workingDir);
        $this->line('');

        // Execute with output streaming
        $output = '';
        $returnCode = 0;

        // Change to project directory
        $oldDir = getcwd();
        chdir($workingDir);

        try {
            // For long-running commands, we need to stream output
            if ($this->isLongRunningCommand($command)) {
                $this->line('<fg=yellow>⏳ This may take a while...</fg=yellow>');
                $this->streamCommandOutput($fullCommand);
            } else {
                // Quick commands can use exec
                exec($fullCommand, $outputArray, $returnCode);
                $output = implode("\n", $outputArray);

                if (!empty($output)) {
                    $this->displayOutput($output);
                } else {
                    $this->line('<fg=yellow>Command executed but produced no output.</fg=yellow>');
                }
            }
        } catch (Exception $e) {
            throw new Exception('Failed to execute composer: ' . $e->getMessage());
        } finally {
            // Restore original directory
            chdir($oldDir);
        }

        if ($returnCode !== 0 && !$this->isLongRunningCommand($command)) {
            $this->error("Command exited with code: $returnCode");
        } else {
            $this->line('<fg=green>✅ Command completed</fg=green>');
        }
    }

    /**
     * Stream output for long-running commands
     *
     * @param string $command
     */
    protected function streamCommandOutput($command)
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]); // Close stdin

            // Read output in real-time
            while (($line = fgets($pipes[1])) !== false) {
                $this->line(rtrim($line));
            }

            // Read any errors
            while (($line = fgets($pipes[2])) !== false) {
                $this->line('<fg=red>' . rtrim($line) . '</fg=red>');
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    /**
     * Check if command is long-running
     *
     * @param string $command
     * @return bool
     */
    protected function isLongRunningCommand($command)
    {
        $longRunningCommands = [
            'install',
            'update',
            'require',
            'remove',
            'create-project',
            'dump-autoload',
            'self-update',
            'global require',
            'global update'
        ];

        foreach ($longRunningCommands as $longCommand) {
            if (strpos($command, $longCommand) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find Composer on shared hosting or local development
     *
     * @return string|false
     */
    protected function findComposerOnSharedHost()
    {
        // Strategy 1: Check if there's a local composer.phar in the project
        $localComposer = base_path('composer.phar');
        if (file_exists($localComposer)) {
            // Find the correct CLI PHP binary (not FPM)
            $phpBinary = $this->findCliPhpBinary();
            return escapeshellarg($phpBinary) . ' ' . escapeshellarg($localComposer);
        }

        // Strategy 2: Try to use 'which' if available
        $whichResult = @shell_exec('which composer 2>/dev/null');
        if (!empty($whichResult)) {
            $composerPath = trim($whichResult);
            if (is_executable($composerPath)) {
                return escapeshellarg($composerPath);
            }
        }

        // Strategy 3: Check if composer is in PATH by trying to run it
        $testOutput = @shell_exec('composer --version 2>/dev/null');
        if (!empty($testOutput) && strpos($testOutput, 'Composer') !== false) {
            return 'composer'; // It's in PATH
        }

        // Strategy 4: Try common shared hosting paths
        $commonPaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/bin/composer',
            '/opt/cpanel/composer/bin/composer', // cPanel
            '/home/composer/composer.phar',      // Some shared hosts
            // Add Homebrew paths for local development
            '/opt/homebrew/bin/composer',        // Apple Silicon Mac
            '/usr/local/bin/composer',           // Intel Mac
        ];

        foreach ($commonPaths as $path) {
            if (is_executable($path)) {
                return escapeshellarg($path);
            }
        }

        // Strategy 5: Look for PHP and try to download composer.phar if we have write permissions
        if (is_writable(base_path())) {
            $this->line('<fg=yellow>Composer not found. Attempting to download composer.phar...</fg=yellow>');
            return $this->downloadComposerPhar();
        }

        return false;
    }

    /**
     * Find the CLI PHP binary (not FPM)
     *
     * @return string
     */
    protected function findCliPhpBinary()
    {
        // If current PHP_BINARY is FPM, find the CLI version
        if (strpos(PHP_BINARY, 'fpm') !== false) {
            // For Herd on macOS, try to find the CLI version
            $phpVersion = PHP_MAJOR_VERSION . PHP_MINOR_VERSION; // e.g., "83"

            $cliPaths = [
                // Herd CLI paths
                str_replace('php' . $phpVersion . '-fpm', 'php' . $phpVersion, PHP_BINARY),
                str_replace('-fpm', '', PHP_BINARY),

                // System paths
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/opt/homebrew/bin/php',

                // Herd alternative paths
                '/Users/' . get_current_user() . '/Library/Application Support/Herd/bin/php' . $phpVersion,

                // Generic
                'php'
            ];

            foreach ($cliPaths as $path) {
                if (is_executable($path)) {
                    // Test if it's CLI (not FPM)
                    $test = @shell_exec(escapeshellarg($path) . ' --version 2>/dev/null');
                    if (!empty($test) && strpos($test, 'PHP') !== false && strpos($test, 'fpm') === false) {
                        return $path;
                    }
                }
            }
        }

        // Fallback: try the current PHP_BINARY anyway
        return PHP_BINARY;
    }

    /**
     * Download composer.phar
     *
     * @return string|false
     */
    protected function downloadComposerPhar()
    {
        try {
            $composerPharPath = base_path('composer.phar');

            // Download composer installer
            $installer = file_get_contents('https://getcomposer.org/installer');
            if (!$installer) {
                return false;
            }

            // Run installer to create composer.phar
            $tempInstaller = base_path('composer-installer.php');
            file_put_contents($tempInstaller, $installer);

            $phpBinary = escapeshellarg($this->findCliPhpBinary());
            $installerPath = escapeshellarg($tempInstaller);
            $output = shell_exec($phpBinary . ' ' . $installerPath . ' 2>&1');
            unlink($tempInstaller);

            if (file_exists($composerPharPath)) {
                $this->line('<fg=green>✅ Successfully downloaded composer.phar</fg=green>');
                return $phpBinary . ' ' . escapeshellarg($composerPharPath);
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Display output with reasonable limits
     *
     * @param string $output
     */
    protected function displayOutput($output)
    {
        $lines = explode("\n", trim($output));

        // Don't truncate for composer - users need full output
        foreach ($lines as $line) {
            $this->line($line);
        }
    }

    /**
     * Show help when Composer is not found
     */
    protected function showComposerNotFoundHelp()
    {
        $this->line('<fg=red>❌ Composer not found</fg=red>');
        $this->line('');
        $this->line('<fg=yellow>For Shared Hosting:</fg=yellow>');
        $this->line('1. Download composer.phar from https://getcomposer.org/composer.phar');
        $this->line('2. Upload to: ' . base_path());
        $this->line('');
        $this->line('<fg=yellow>For Local Development:</fg=yellow>');
        $this->line('1. Install via Homebrew: brew install composer');
        $this->line('2. Or download from: https://getcomposer.org/');
        $this->line('');
        $this->line('<fg=blue>Once available, you can use any Composer command!</fg=blue>');
    }

    /**
     * Show available Composer commands
     */
    protected function showHelp()
    {
        $this->line('<fg=cyan>Full-Featured Composer Terminal</fg=cyan>');
        $this->line('');
        $this->line('<fg=green>All Composer commands are available:</fg=green>');
        $this->line('');
        $this->line('<fg=blue>Package Management:</fg=blue>');
        $this->line('  composer install                Install dependencies');
        $this->line('  composer update                 Update dependencies');
        $this->line('  composer require vendor/package Add new package');
        $this->line('  composer remove vendor/package  Remove package');
        $this->line('  composer show                   List packages');
        $this->line('  composer outdated               Show outdated packages');
        $this->line('');
        $this->line('<fg=blue>Information & Validation:</fg=blue>');
        $this->line('  composer --version              Show Composer version');
        $this->line('  composer validate               Validate composer.json');
        $this->line('  composer check-platform-reqs   Check requirements');
        $this->line('  composer diagnose               Diagnose issues');
        $this->line('  composer show vendor/package    Show package details');
        $this->line('');
        $this->line('<fg=blue>Maintenance:</fg=blue>');
        $this->line('  composer dump-autoload          Regenerate autoloader');
        $this->line('  composer clear-cache            Clear cache');
        $this->line('  composer self-update            Update Composer');
        $this->line('');
        $this->line('<fg=yellow>💪 Full power for shared hosting management!</fg=yellow>');
        $this->line('<fg=red>⚠️  Use with caution on production sites</fg=red>');
    }
}
