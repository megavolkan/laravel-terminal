<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Recca0120\Terminal\Console\Commands\Concerns\ResolvesProjectPath;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Tail extends Command
{
    use ResolvesProjectPath;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'tail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'tail command';

    /**
     * $files.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * __construct.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Handle the command.
     *
     * @throws \InvalidArgumentException
     */
    public function handle()
    {
        $path = $this->argument('path');
        $lines = max(1, (int) $this->option('lines'));

        if (empty($path) === false) {
            $file = $this->resolveProjectPath($path);

            if ($file === null) {
                $this->outsideProjectError($path);

                return 1;
            }
        } else {
            $storage = function_exists('storage_path') === true ? storage_path() : getcwd();
            $storage = rtrim($storage, '/').'/';

            $file = (new Collection($this->files->glob($storage.'logs/*.log')))
                ->filter(function ($file) {
                    return is_file($file);
                })->sortByDesc(function ($file) {
                    return filemtime($file);
                })->first();

            if ($file === null) {
                $this->error('tail: '.$storage.'logs dizininde okunabilir bir .log dosyası yok');

                return 1;
            }
        }

        $this->readLine($file, $lines);

        return 0;
    }

    /**
     * Dosyanın SON $lines satırını yazar.
     *
     * Dosya sonundan başlayıp geriye doğru parça parça okunur; böylece
     * büyük log dosyaları belleğe tümüyle yüklenmez.
     *
     * @param  string  $file
     * @param  int  $lines
     */
    protected function readLine($file, $lines = 50)
    {
        if (is_file($file) === false) {
            $this->error('tail: cannot open ‘'.$file.'’ for reading: No such file or directory');

            return;
        }

        $fp = fopen($file, 'rb');

        if ($fp === false) {
            $this->error('tail: cannot open ‘'.$file.'’ for reading: Permission denied');

            return;
        }

        $chunkSize = 4096;
        $buffer = '';

        fseek($fp, 0, SEEK_END);
        $position = ftell($fp);

        // Aranan satır sayısına ulaşana ya da dosya başına varana kadar geri git
        while ($position > 0 && substr_count($buffer, "\n") <= $lines) {
            $read = (int) min($chunkSize, $position);
            $position -= $read;
            fseek($fp, $position, SEEK_SET);
            $buffer = fread($fp, $read).$buffer;
        }

        fclose($fp);

        $all = explode("\n", rtrim($buffer, "\n"));

        $this->line(implode("\n", array_slice($all, -$lines)));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['path', InputArgument::OPTIONAL, 'path'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['lines', null, InputOption::VALUE_OPTIONAL, 'output the last K lines, instead of the last 50', 50],
        ];
    }
}
