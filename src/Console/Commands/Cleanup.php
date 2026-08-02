<?php

namespace Recca0120\Terminal\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Glob\Glob;

class Cleanup extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'cleanup vendor folder';

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
     * @throws InvalidArgumentException
     */
    public function handle()
    {
        if ((bool) $this->option('force') === false) {
            $this->warnBeforeDelete();

            return 1;
        }

        set_time_limit(0);
        $root = function_exists('base_path') === true ? base_path() : getcwd();
        $root = rtrim($root, '/').'/';

        $docs = ['README*', 'CHANGELOG*', 'FAQ*', 'CONTRIBUTING*', 'HISTORY*', 'UPGRADING*', 'UPGRADE*', 'package*', 'demo', 'example', 'examples', 'doc', 'docs', 'readme*'];
        $tests = ['.travis.yml', '.scrutinizer.yml', 'phpunit.xml*', 'phpunit.php', 'test', 'Test', 'tests', 'Tests', 'travis'];
        $vcs = ['.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg'];
        $others = [
            'vendor',
            '.babelrc',
            '.editorconfig',
            '.eslintrc.*',
            '.gitattributes',
            '.gitignore',
            '.nitpick.json',
            '.php_cs',
            '.scrutinizer.yml',
            '.styleci.yml',
            '.travis.yml',
            'appveyor.yml',
            'package.json',
            'phpcs.xml',
            'ruleset.xml',
        ];
        $common = [
            'node_modules',
        ];

        (new Collection(
            Glob::glob($root.'{'.(new Collection(Glob::glob($root.'vendor/*/*')))
                ->map(function ($item) {
                    return substr($item, strpos($item, 'vendor'));
                })
                ->implode(',').'}/**/{'.(implode(',', array_merge($vcs, $common, $others, $tests, $docs))).'}')
        ))
            ->merge(Glob::glob($root.'{'.(implode(',', array_merge($vcs, $common))).'}'))
            ->merge([
                $root.'vendor/phpunit',
            ])
            ->filter()
            ->each(function ($item) {
                if ($this->files->isDirectory($item) === true) {
                    $this->files->deleteDirectory($item);
                    $this->info('delete directory: '.$item);
                } elseif ($this->files->isFile($item)) {
                    $this->files->delete($item);
                    $this->info('delete file: '.$item);
                }
            });

        $this->line('');

        return 0;
    }

    /**
     * Web terminal etkileşimli soru soramaz (input her zaman non-interactive
     * çalışır); bu yüzden onay, açık bir --force bayrağıyla alınır.
     */
    protected function warnBeforeDelete(): void
    {
        $this->line('<fg=red>DİKKAT: cleanup geri alınamaz dosya silme işlemi yapar.</fg=red>');
        $this->line('');
        $this->line('Silinecekler:');
        $this->line('  - vendor/*/* içindeki test, doküman ve VCS klasörleri');
        $this->line('  - <fg=yellow>vendor/phpunit</fg=yellow>');
        $this->line('  - proje kökündeki <fg=yellow>node_modules</fg=yellow>, <fg=yellow>.git</fg=yellow>, <fg=yellow>.svn</fg=yellow>');
        $this->line('');
        $this->line('SSH erişimi olmayan bir sunucuda silinen dosyaları geri getirmek için');
        $this->line('vendor klasörünü yeniden yüklemeniz gerekebilir.');
        $this->line('');
        $this->line('Devam etmek için: <fg=yellow>cleanup --force</fg=yellow>');
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Onay istemeden sil'],
        ];
    }
}
