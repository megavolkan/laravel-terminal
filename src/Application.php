<?php

namespace Recca0120\Terminal;

use Exception;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Http\Request;
use Recca0120\Terminal\Contracts\TerminalCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends ConsoleApplication
{
    /**
     * Run an Artisan console command by name.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $outputBuffer
     * @return int
     *
     * @throws \Exception
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        if ($this->ajax() === true) {
            $this->lastOutput = $outputBuffer ?: new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true, new OutputFormatter(true));
            $this->setCatchExceptions(true);
        } else {
            $this->lastOutput = $outputBuffer ?: new BufferedOutput();
            $this->setCatchExceptions(false);
        }

        // Build command string more safely
        $commandString = $command;
        if (!empty($parameters)) {
            // Special handling for tinker command - DON'T add --command= prefix
            if ($command === 'tinker' && count($parameters) > 0) {
                // For tinker, pass the code directly as --command option value
                $tinkCommand = $parameters[0];
                $commandString = 'tinker --command=' . escapeshellarg($tinkCommand);
            } else {
                // For other commands, add parameters normally
                foreach ($parameters as $parameter) {
                    if (is_string($parameter)) {
                        // Escape parameters that contain spaces
                        $parameter = escapeshellarg($parameter);
                        $commandString .= ' ' . $parameter;
                    }
                }
            }
        }

        try {
            $input = new StringInput($commandString);
            $input->setInteractive(false);
            $result = $this->run($input, $this->lastOutput);
        } catch (Exception $e) {
            // Handle exceptions more gracefully
            if ($this->lastOutput instanceof BufferedOutput) {
                $this->lastOutput->write('Error: ' . $e->getMessage());
            }
            $result = 1;
        } finally {
            $this->setCatchExceptions(true);
        }

        return $result;
    }

    /**
     * Resolve an array of commands through the application.
     *
     * @param  array|mixed  $commands
     * @return $this
     */
    public function resolveCommands($commands)
    {
        $validCommands = array_filter($commands, static function ($command) {
            return is_subclass_of($command, TerminalCommand::class);
        });

        return parent::resolveCommands($validCommands);
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        if ($this->lastOutput instanceof BufferedOutput) {
            return $this->lastOutput->fetch();
        }

        return '';
    }

    /**
     * Check if the request is an AJAX request.
     *
     * @return bool
     */
    private function ajax()
    {
        try {
            $request = $this->laravel['request'] ?? Request::capture();
            return $request->ajax();
        } catch (Exception $e) {
            return false;
        }
    }
}
