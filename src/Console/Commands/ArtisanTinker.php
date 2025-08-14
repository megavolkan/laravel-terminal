<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Console\Command;
use Recca0120\Terminal\Contracts\TerminalCommand;
use Illuminate\Support\Facades\Cache;
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
    protected $description = 'Interact with your application in tinker mode with persistent variables';

    /**
     * Session ID for variable persistence
     *
     * @var string
     */
    protected $sessionId;

    /**
     * Cache key for storing variables
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * Cache expiration time in minutes
     *
     * @var int
     */
    protected $cacheExpiration = 60;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Initialize session for variable persistence
        $this->initializeSession();

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

        // Handle special commands
        if ($this->handleSpecialCommands(trim($command))) {
            return 0;
        }

        // If no command provided, show help
        if (empty(trim($command))) {
            $this->showHelp();
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
     * Initialize session for variable persistence
     */
    protected function initializeSession()
    {
        // Create a session ID based on user and current time (with some persistence)
        // This allows variables to persist for a reasonable time while preventing conflicts
        $userIdentifier = $_SERVER['REMOTE_ADDR'] ?? 'local';
        $timeWindow = floor(time() / (5 * 60)); // 5-minute windows
        
        $this->sessionId = 'tinker_session_' . md5($userIdentifier . $timeWindow);
        $this->cacheKey = 'terminal_tinker_vars_' . $this->sessionId;
    }

    /**
     * Handle special commands like clear, reset, vars
     *
     * @param string $command
     * @return bool
     */
    protected function handleSpecialCommands($command)
    {
        $command = strtolower($command);

        switch ($command) {
            case 'exit':
            case 'quit':
                $this->line('<fg=green>Goodbye! Variables cleared.</fg=green>');
                $this->clearVariables();
                return true;

            case 'clear':
            case 'reset':
                $this->clearVariables();
                $this->line('<fg=green>Variables cleared!</fg=green>');
                $this->getOutput()->write('<fg=magenta>>>> </fg=magenta>');
                return true;

            case 'vars':
            case 'variables':
                $this->showVariables();
                $this->getOutput()->write('<fg=magenta>>>> </fg=magenta>');
                return true;

            case '':
                return false; // Let it show help

            default:
                return false;
        }
    }

    /**
     * Show help information
     */
    protected function showHelp()
    {
        $this->line('<fg=cyan>Laravel Terminal Tinker Mode - With Persistent Variables</fg=cyan>');
        $this->line('');
        $this->line('<fg=yellow>Variables now persist between commands!</fg=yellow>');
        $this->line('');
        $this->line('<fg=green>Examples:</fg=green>');
        $this->line('  <fg=white>$number = 42</fg=white>');
        $this->line('  <fg=white>$user = User::first()</fg=white>');
        $this->line('  <fg=white>$user->name</fg=white>                 <-- Variables persist!');
        $this->line('  <fg=white>$posts = Post::limit(5)->get()</fg=white>');
        $this->line('  <fg=white>$posts->count()</fg=white>');
        $this->line('');
        $this->line('<fg=green>Quick expressions:</fg=green>');
        $this->line('  <fg=white>1 + 1</fg=white>');
        $this->line('  <fg=white>App::version()</fg=white>');
        $this->line('  <fg=white>config("app.name")</fg=white>');
        $this->line('  <fg=white>User::count()</fg=white>');
        $this->line('');
        $this->line('<fg=green>Special commands:</fg=green>');
        $this->line('  <fg=white>vars</fg=white>                        Show stored variables');
        $this->line('  <fg=white>clear</fg=white>                       Clear all variables');
        $this->line('  <fg=white>exit</fg=white>                        Exit and clear variables');
        $this->line('');
        $this->line('<fg=blue>Variables expire after ' . $this->cacheExpiration . ' minutes of inactivity.</fg=blue>');
        $this->getOutput()->write('<fg=magenta>>>> </fg=magenta>');
    }

    /**
     * Show current variables
     */
    protected function showVariables()
    {
        $variables = $this->getStoredVariables();
        
        if (empty($variables)) {
            $this->line('<fg=yellow>No variables stored yet.</fg=yellow>');
            return;
        }

        $this->line('<fg=cyan>Stored Variables:</fg=cyan>');
        
        foreach ($variables as $name => $info) {
            $type = $info['type'] ?? 'unknown';
            $value = $info['display'] ?? 'N/A';
            
            $this->line("  <fg=green>\${$name}</fg=green> <fg=blue>({$type})</fg=blue> = <fg=white>{$value}</fg=white>");
        }
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
        $modelNames = ['User', 'Category', 'Product', 'Post', 'Order', 'Customer', 'Item', 'Tag', 'Role', 'Permission'];

        foreach ($modelNames as $modelName) {
            // Only add namespace if it doesn't already have one
            if (strpos($command, '\\') === false && preg_match("/\b{$modelName}::/", $command)) {
                $command = str_replace("{$modelName}::", "\\App\\Models\\{$modelName}::", $command);
            }
        }

        // Handle AppModelsXxx patterns
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
     * Execute PHP code with variable persistence
     *
     * @param string $code
     * @return mixed
     */
    protected function executeCode($code)
    {
        try {
            // Clean the code input
            $code = trim($code);
            $code = rtrim($code, ';');

            // Get stored variables
            $variables = $this->getStoredVariables();

            // Build the execution context with existing variables
            $contextCode = $this->buildExecutionContext($variables);
            
            // Determine if this is an assignment or expression
            $isAssignment = $this->isAssignment($code);
            
            if ($isAssignment) {
                // For assignments, execute and capture new variables
                $fullCode = $contextCode . "\n" . $code . ";\n" . $this->getVariableCaptureCode();
            } else {
                // For expressions, return the result
                if (!preg_match('/^(return\s|echo\s|print\s|var_dump\s|dump\s)/i', $code) &&
                    !preg_match('/^<\?php/', $code) &&
                    !preg_match('/^(if|for|foreach|while|switch|try|class|function|namespace)\s/i', $code)
                ) {
                    $fullCode = $contextCode . "\nreturn " . $code . ";";
                } else {
                    $fullCode = $contextCode . "\n" . $code . ";";
                }
            }

            // Capture output
            ob_start();

            // Execute the code
            $result = eval($fullCode);

            // Get any output that was generated
            $output = ob_get_clean();

            // Display any captured output first
            if (!empty($output)) {
                $this->line($output);
            }

            // Handle variable updates for assignments
            if ($isAssignment && isset($result['variables'])) {
                $this->updateStoredVariables($result['variables']);
                $result = $result['result'] ?? null;
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
     * Check if code is a variable assignment
     *
     * @param string $code
     * @return bool
     */
    protected function isAssignment($code)
    {
        return preg_match('/^\s*\$[a-zA-Z_][a-zA-Z0-9_]*\s*=/', $code);
    }

    /**
     * Build execution context with stored variables
     *
     * @param array $variables
     * @return string
     */
    protected function buildExecutionContext($variables)
    {
        $context = [];
        
        foreach ($variables as $name => $info) {
            if (isset($info['serialized'])) {
                $context[] = "\${$name} = unserialize(" . var_export($info['serialized'], true) . ");";
            }
        }
        
        return implode("\n", $context);
    }

    /**
     * Get code to capture variables after execution
     *
     * @return string
     */
    protected function getVariableCaptureCode()
    {
        return '
            $__captured_vars = [];
            foreach (get_defined_vars() as $__var_name => $__var_value) {
                if (!in_array($__var_name, ["__captured_vars", "__var_name", "__var_value"])) {
                    $__captured_vars[$__var_name] = $__var_value;
                }
            }
            return ["variables" => $__captured_vars, "result" => isset($result) ? $result : null];
        ';
    }

    /**
     * Get stored variables from cache
     *
     * @return array
     */
    protected function getStoredVariables()
    {
        return Cache::get($this->cacheKey, []);
    }

    /**
     * Update stored variables in cache
     *
     * @param array $variables
     */
    protected function updateStoredVariables($variables)
    {
        $storedVars = [];
        
        foreach ($variables as $name => $value) {
            $storedVars[$name] = [
                'type' => gettype($value),
                'display' => $this->getDisplayValue($value),
                'serialized' => serialize($value)
            ];
        }
        
        Cache::put($this->cacheKey, $storedVars, now()->addMinutes($this->cacheExpiration));
    }

    /**
     * Get display value for variable
     *
     * @param mixed $value
     * @return string
     */
    protected function getDisplayValue($value)
    {
        switch (gettype($value)) {
            case 'object':
                if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                    return class_basename($value) . ' #' . ($value->getKey() ?? '?');
                } elseif ($value instanceof \Illuminate\Database\Eloquent\Collection) {
                    return 'Collection (' . $value->count() . ' items)';
                } else {
                    return class_basename($value);
                }
            case 'array':
                return 'Array (' . count($value) . ' items)';
            case 'string':
                return strlen($value) > 50 ? '"' . substr($value, 0, 47) . '..."' : '"' . $value . '"';
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'null':
                return 'null';
            default:
                return (string) $value;
        }
    }

    /**
     * Clear stored variables
     */
    protected function clearVariables()
    {
        Cache::forget($this->cacheKey);
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
