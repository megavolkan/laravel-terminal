<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Console\Command;
use Recca0120\Terminal\Contracts\TerminalCommand;
use Throwable;

class ArtisanTinker extends Command implements TerminalCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tinker {--command= : The command to execute in tinker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interact with your application in tinker mode';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $command = $this->option('command') ?? '';

        // Clean up the command - handle various formats
        if (strpos($command, '--command=') === 0) {
            if (preg_match('/^--command=["\'](.*)["\']\s*$/', $command, $matches)) {
                $command = $matches[1];
            } elseif (preg_match('/^--command=(.*)$/', $command, $matches)) {
                $command = $matches[1];
            }
        }

        // Additional cleanup for shell escaping issues
        $command = $this->cleanCommand($command);

        // If no command provided, show help
        if (empty(trim($command))) {
            $this->line('<fg=cyan>Laravel Terminal Tinker Mode</fg=cyan>');
            $this->line('');
            $this->line('Enter PHP expressions. Examples:');
            $this->line('  <fg=green>Basic:</fg=green>');
            $this->line('    1 + 1');
            $this->line('    phpversion()');
            $this->line('    date(Y-m-d)        <-- Auto-fixes to date("Y-m-d")');
            $this->line('');
            $this->line('  <fg=green>Laravel:</fg=green>');
            $this->line('    App::version()');
            $this->line('    config(app.name)   <-- Auto-fixes to config("app.name")');
            $this->line('    env(APP_ENV)');
            $this->line('');
            $this->line('  <fg=green>Models:</fg=green>');
            $this->line('    User::count()                    <-- Auto-fixes namespace');
            $this->line('    AppModelsCategory::all()         <-- Auto-fixes to \\App\\Models\\Category::all()');
            $this->line('    \\App\\Models\\Category::first()   <-- Use double backslashes if needed');
            $this->line('');
            $this->getOutput()->write('<fg=magenta>>>> </fg=magenta>');
            return 0;
        }

        // Show what command we're executing
        $this->getOutput()->write('<fg=magenta>>>> </fg=magenta><fg=white>' . $command . '</fg=white>');
        $this->line('');

        $this->executeCode($command);

        // Add a prompt for the next command
        $this->line('');
        $this->getOutput()->write('<fg=magenta>>>> </fg=magenta>');

        return 0;
    }

    /**
     * Clean command input and fix common quote issues
     *
     * @param string $command
     * @return string
     */
    protected function cleanCommand($command)
    {
        // Remove outer shell quotes if present
        if ((substr($command, 0, 1) === '"' && substr($command, -1) === '"') ||
            (substr($command, 0, 1) === "'" && substr($command, -1) === "'")
        ) {
            $command = substr($command, 1, -1);
        }

        // Handle escaped quotes
        $command = str_replace(['\\"', "\\'"], ['"', "'"], $command);

        // Fix common quote-stripping issues
        $command = $this->fixMissingQuotes($command);

        return $command;
    }

    /**
     * Fix missing quotes and namespace separators in common patterns
     *
     * @param string $command
     * @return string
     */
    protected function fixMissingQuotes($command)
    {
        // First fix missing namespace separators
        $command = $this->fixNamespaces($command);

        // Then fix missing quotes in function calls
        $patterns = [
            // date(Y-m-d) -> date("Y-m-d")
            '/\bdate\s*\(\s*([A-Za-z0-9\-_\.]+)\s*\)/' => 'date("$1")',

            // config(app.name) -> config("app.name")
            '/\bconfig\s*\(\s*([A-Za-z0-9\-_\.]+)\s*\)/' => 'config("$1")',

            // env(KEY) -> env("KEY")
            '/\benv\s*\(\s*([A-Za-z0-9\-_\.]+)\s*\)/' => 'env("$1")',

            // cache(key) -> cache("key")
            '/\bcache\s*\(\s*([A-Za-z0-9\-_\.]+)\s*\)/' => 'cache("$1")',

            // view(template.name) -> view("template.name")
            '/\bview\s*\(\s*([A-Za-z0-9\-_\.]+)\s*\)/' => 'view("$1")',

            // route(name) -> route("name")
            '/\broute\s*\(\s*([A-Za-z0-9\-_\.]+)\s*\)/' => 'route("$1")',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $command = preg_replace($pattern, $replacement, $command);
        }

        return $command;
    }

    /**
     * Fix missing namespace separators
     *
     * @param string $command
     * @return string
     */
    protected function fixNamespaces($command)
    {
        // Handle common Laravel model names that might lose their namespace
        // Do this FIRST, before other patterns
        $modelNames = ['User', 'Category', 'Product', 'Post', 'Order', 'Customer', 'Item', 'Tag', 'Role', 'Permission'];

        foreach ($modelNames as $modelName) {
            // Only add namespace if it doesn't already have one
            if (strpos($command, '\\') === false && preg_match("/\b{$modelName}::/", $command)) {
                $command = str_replace("{$modelName}::", "\\App\\Models\\{$modelName}::", $command);
            }
        }

        // Handle AppModelsXxx patterns using callback
        $command = preg_replace_callback(
            '/\bAppModels([A-Z][A-Za-z0-9_]*)\b/',
            function ($matches) {
                return '\\App\\Models\\' . $matches[1];
            },
            $command
        );

        // Handle other patterns
        $command = preg_replace_callback(
            '/\bAppHttpControllers([A-Z][A-Za-z0-9_]*)\b/',
            function ($matches) {
                return '\\App\\Http\\Controllers\\' . $matches[1];
            },
            $command
        );

        return $command;
    }

    /**
     * Execute PHP code safely.
     *
     * @param string $code
     * @return mixed
     */
    protected function executeCode($code)
    {
        // Handle exit command
        if (trim(strtolower($code)) === 'exit') {
            $this->line('<fg=green>Goodbye</fg=green>');
            return null;
        }

        try {
            // Clean the code input
            $code = trim($code);

            // Remove trailing semicolon if present
            $code = rtrim($code, ';');

            // Prepare code for evaluation
            $evalCode = $code;

            // If the code doesn't start with certain keywords, make it an expression
            if (
                !preg_match('/^(return\s|echo\s|print\s|var_dump\s|dump\s|\$|use\s)/i', $code) &&
                !preg_match('/^<\?php/', $code) &&
                !preg_match('/^(if|for|foreach|while|switch|try|class|function|namespace)\s/i', $code)
            ) {
                $evalCode = 'return ' . $code . ';';
            } else {
                $evalCode = $code . ';';
            }

            // Capture output
            ob_start();

            // Execute the code
            $result = eval($evalCode);

            // Get any output that was generated
            $output = ob_get_clean();

            // Display any captured output first
            if (!empty($output)) {
                $this->line($output);
            }

            // Display the result
            $this->displayResult($result);

            return $result;
        } catch (Throwable $e) {
            // Clean up output buffer
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->line('<fg=red>' . get_class($e) . '</fg=red>: <fg=white>' . $e->getMessage() . '</fg=white>');

            return null;
        }
    }

    /**
     * Display the result of code execution
     *
     * @param mixed $result
     */
    protected function displayResult($result)
    {
        $this->getOutput()->write('<fg=blue>=></fg=blue> ');

        if ($result === null) {
            $this->line('<fg=yellow>null</fg=yellow>');
            return;
        }

        switch (gettype($result)) {
            case 'object':
                if (method_exists($result, '__toString')) {
                    $this->line('<fg=green>"' . (string) $result . '"</fg=green>');
                } elseif ($result instanceof \Illuminate\Database\Eloquent\Collection) {
                    $this->line('<fg=cyan>Collection</fg=cyan> (' . $result->count() . ' items)');
                } elseif ($result instanceof \Illuminate\Database\Eloquent\Model) {
                    $this->line('<fg=cyan>' . class_basename($result) . '</fg=cyan> #' . ($result->getKey() ?? '?'));
                } else {
                    $this->line('<fg=cyan>' . class_basename($result) . '</fg=cyan>');
                }
                break;

            case 'array':
                $count = count($result);
                if ($count <= 5) {
                    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $this->line('<fg=yellow>Array</fg=yellow> (' . $count . ' items) [');
                    $slice = array_slice($result, 0, 3, true);
                    foreach ($slice as $key => $value) {
                        $displayValue = is_string($value) ? '"' . $value . '"' : json_encode($value);
                        $this->line('  ' . json_encode($key) . ' => ' . $displayValue);
                    }
                    $this->line('  ... and ' . ($count - 3) . ' more');
                    $this->line(']');
                }
                break;

            case 'string':
                $this->line('<fg=green>"' . $result . '"</fg=green>');
                break;

            case 'boolean':
                $this->line('<fg=yellow>' . ($result ? 'true' : 'false') . '</fg=yellow>');
                break;

            case 'integer':
            case 'double':
                $this->line('<fg=blue>' . $result . '</fg=blue>');
                break;

            default:
                $this->line(var_export($result, true));
                break;
        }
    }
}
