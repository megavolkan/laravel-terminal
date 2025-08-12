<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Contracts\Console\Kernel as ArtisanContract;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Input\StringInput;
use Recca0120\Terminal\Contracts\TerminalCommand;

class Artisan extends Command implements TerminalCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'artisan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Laravel Artisan Commands - Full Support';

    /**
     * Commands not supported in web terminal for security.
     *
     * @var array
     */
    protected $notSupported = [
        'down' => 'Maintenance mode should be handled through deployment',
        'serve' => 'Development server not applicable in web terminal',
    ];

    /**
     * $artisan.
     *
     * @var ArtisanContract
     */
    protected $artisan;

    /**
     * __construct.
     */
    public function __construct(ArtisanContract $artisan)
    {
        parent::__construct();
        $this->artisan = $artisan;
    }

    /**
     * Configure the command.
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('command', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The artisan command to execute');
    }

    /**
     * Handle the command.
     *
     * @throws InvalidArgumentException
     */
    public function handle()
    {
        // Get the full command line from the web terminal
        $commandLine = $this->getFullCommandLine();

        if (empty($commandLine)) {
            $commandLine = 'list';
        }

        // Clean and fix the command
        $commandLine = $this->fixCommand(trim($commandLine));
        
        // Remove problematic quotes that might be contaminating arguments
        $commandLine = $this->cleanQuotes($commandLine);

        // Check for unsupported commands
        $firstArgument = explode(' ', $commandLine)[0];
        if (isset($this->notSupported[$firstArgument])) {
            $this->error('Command "' . $firstArgument . '" is not supported in web terminal.');
            $this->line('Reason: ' . $this->notSupported[$firstArgument]);
            return 1;
        }

        try {
            // Create input for the artisan command
            $input = new StringInput($commandLine);
            $input->setInteractive(false);

            // Execute the command through Laravel's artisan kernel
            $exitCode = $this->artisan->handle($input, $this->getOutput());

            return $exitCode;
        } catch (\Exception $e) {
            $this->error('Error executing command: ' . $e->getMessage());
            $this->line('Command attempted: ' . $commandLine);
            return 1;
        }
    }

    /**
     * Get the full command line from various sources
     */
    protected function getFullCommandLine()
    {
        // Method 1: From --command option (current method)
        $command = $this->option('command');
        if (!empty($command)) {
            return $command;
        }

        // Method 2: Try to reconstruct from server/request data
        // This handles the case where the web terminal passes the command differently
        if (isset($_SERVER['argv'])) {
            $argv = $_SERVER['argv'];
            // Find artisan command and get everything after it
            $artisanIndex = array_search('artisan', $argv);
            if ($artisanIndex !== false && isset($argv[$artisanIndex + 1])) {
                return implode(' ', array_slice($argv, $artisanIndex + 1));
            }
        }

        // Method 3: From request if available (web terminal specific)
        if (function_exists('request') && request()->has('method')) {
            $method = request('method');
            $params = request('params', []);

            if ($method === 'artisan' && !empty($params)) {
                return implode(' ', $params);
            }
        }

        return '';
    }

    /**
     * Fix command with necessary options for web terminal execution.
     *
     * @param  string  $command
     * @return string
     */
    protected function fixCommand($command)
    {
        // Add --force to migration commands for non-interactive execution
        $isMigrateCommand = Str::startsWith($command, 'migrate') &&
            !Str::startsWith($command, 'migrate:status') &&
            !Str::startsWith($command, 'migrate:rollback');

        if ($isMigrateCommand && !str_contains($command, '--force')) {
            $command .= ' --force';
        }

        // Add --force to seeder commands
        if (Str::startsWith($command, 'db:seed') && !str_contains($command, '--force')) {
            $command .= ' --force';
        }

        // Add --all to vendor:publish if no specific options
        if (
            Str::startsWith($command, 'vendor:publish') &&
            !str_contains($command, '--provider') &&
            !str_contains($command, '--tag') &&
            !str_contains($command, '--all')
        ) {
            $command .= ' --all';
        }

        // Ensure no-interaction for all commands
        if (!str_contains($command, '--no-interaction')) {
            $command .= ' --no-interaction';
        }

        // Special handling for make commands - these should work as-is
        // The issue might be in how the web terminal framework parses them

        return $command;
    }

    /**
     * Clean quotes from command line that might contaminate arguments.
     *
     * @param  string  $commandLine
     * @return string
     */
    protected function cleanQuotes($commandLine)
    {
        // Remove surrounding quotes from the entire command
        $commandLine = trim($commandLine, '"\'');
        
        // Handle cases where quotes are mixed into arguments
        // Split by spaces, clean each part, then rejoin
        $parts = explode(' ', $commandLine);
        $cleanParts = [];
        
        foreach ($parts as $part) {
            // Remove quotes from individual arguments
            $cleanPart = trim($part, '"\'');
            if (!empty($cleanPart)) {
                $cleanParts[] = $cleanPart;
            }
        }
        
        return implode(' ', $cleanParts);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['command', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The artisan command to execute'],
        ];
    }
}
