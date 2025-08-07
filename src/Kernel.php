<?php

namespace Recca0120\Terminal;

use Exception;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Arr;
use Recca0120\Terminal\Application as Artisan;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel implements KernelContract
{
    /**
     * The Artisan application instance.
     *
     * @var \Recca0120\Terminal\Application
     */
    protected $artisan;

    /**
     * $config.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new console kernel instance.
     *
     * @param  \Recca0120\Terminal\Application  $artisan
     * @param  array  $config
     */
    public function __construct(Artisan $artisan, $config = [])
    {
        $this->artisan = $artisan;
        $this->config = Arr::except(array_merge([
            'username' => 'LARAVEL',
            'hostname' => php_uname('n'),
            'os' => PHP_OS,
        ], $config), ['enabled', 'whitelists', 'route', 'commands']);
    }

    /**
     * getConfig.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap()
    {
        // Bootstrap is handled automatically in Laravel 11+
    }

    /**
     * Handle an incoming console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $output
     * @return int
     *
     * @throws \Exception
     */
    public function handle($input, $output = null)
    {
        $this->bootstrap();

        return $this->artisan->run($input, $output);
    }

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
        $this->bootstrap();

        return $this->artisan->call($command, $parameters, $outputBuffer);
    }

    /**
     * Queue an Artisan console command by name.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Illuminate\Foundation\Bus\PendingDispatch|void
     */
    public function queue($command, array $parameters = [])
    {
        $this->bootstrap();

        if (class_exists(QueuedCommand::class)) {
            return QueuedCommand::dispatch(func_get_args());
        }

        // Fallback for older versions
        $app = $this->artisan->getLaravel();
        if ($app->bound(Queue::class)) {
            $app[Queue::class]->push(
                'Illuminate\Foundation\Console\QueuedJob',
                func_get_args()
            );
        }
    }

    /**
     * Get all of the commands registered with the console.
     *
     * @return array
     */
    public function all()
    {
        $this->bootstrap();

        return $this->artisan->all();
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        $this->bootstrap();

        return $this->artisan->output();
    }

    /**
     * Terminate the application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  int  $status
     * @return void
     */
    public function terminate($input, $status)
    {
        $this->bootstrap();

        // Call terminate if method exists
        if (method_exists($this->artisan, 'terminate')) {
            $this->artisan->terminate();
        }
    }

    /**
     * Set the Artisan commands provided by the application.
     *
     * @param  array  $commands
     * @return $this
     */
    public function addCommands(array $commands)
    {
        $this->artisan->addCommands($commands);

        return $this;
    }

    /**
     * Set the paths that should have their Artisan commands automatically discovered.
     *
     * @param  array  $paths
     * @return $this
     */
    public function addCommandPaths(array $paths)
    {
        // This is a no-op in the terminal context
        return $this;
    }

    /**
     * Set the paths that should have their Artisan "routes" automatically discovered.
     *
     * @param  array  $paths
     * @return $this
     */
    public function addCommandRoutePaths(array $paths)
    {
        // This is a no-op in the terminal context
        return $this;
    }

    /**
     * Magic method to proxy calls to the underlying artisan application.
     *
     * @param  string  $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $this->bootstrap();

        return call_user_func_array([$this->artisan, $name], $arguments);
    }
}
