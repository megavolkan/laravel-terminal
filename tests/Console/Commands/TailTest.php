<?php

namespace Recca0120\Terminal\Tests\Console\Commands;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Recca0120\Terminal\Console\Commands\Tail;
use Symfony\Component\Console\Tester\CommandTester;
use Webmozart\Glob\Glob;

class TailTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $structure = [
        'logs' => [
            '1.log' => '
                    1.log
                    1.log
                    1.log
                    1.log
                    1.log
                    1.log
                    1.log
                    1.log
                    1.log
                    1.log
                ',
            '2.log' => '
                    2.log
                    2.log
                    2.log
                    2.log
                    2.log
                    2.log
                    2.log
                    2.log
                    2.log
                    2.log
                ',
            '3.log' => '
                    3.log
                    3.log
                    3.log
                    3.log
                    3.log
                    3.log
                    3.log
                    3.log
                    3.log
                    3.log
                ',
            '4.log' => '
                    4.log
                    4.log
                    4.log
                    4.log
                    4.log
                    4.log
                    4.log
                    4.log
                    4.log
                    4.log
                ',
            '5.log' => '
                    5.log
                    5.log
                    5.log
                    5.log
                    5.log
                    5.log
                    5.log
                    5.log
                    5.log
                    5.log
                ',
        ],
    ];

    public function test_tail_default_file()
    {
        $commandTester = new CommandTester($this->getCommand());

        $commandTester->execute([]);

        self::assertStringContainsString('5.log', $commandTester->getDisplay());
    }

    public function test_tail_file()
    {
        $commandTester = new CommandTester($this->getCommand());

        $commandTester->execute(['path' => 'logs/1.log']);

        self::assertStringContainsString('1.log', $commandTester->getDisplay());
    }

    public function test_tail_returns_last_lines_not_first()
    {
        $root = vfsStream::setup('root', null, [
            'app.log' => "satir1\nsatir2\nsatir3\nsatir4\nsatir5\nsatir6\nsatir7\nSON\n",
        ]);

        $commandTester = new CommandTester($this->getCommandForRoot($root));
        $commandTester->execute(['path' => 'app.log', '--lines' => 3]);

        $display = $commandTester->getDisplay();

        self::assertStringContainsString('SON', $display);
        self::assertStringContainsString('satir6', $display);
        self::assertStringNotContainsString('satir1', $display);
    }

    public function test_tail_rejects_path_outside_project()
    {
        $root = vfsStream::setup('root', null, ['app.log' => "foo\n"]);

        $commandTester = new CommandTester($this->getCommandForRoot($root));
        $exitCode = $commandTester->execute(['path' => '../../etc/passwd']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('proje dizininin dışında', $commandTester->getDisplay());
    }

    protected function giveRoot()
    {
        $root = vfsStream::setup('root', null, $this->structure);
        $i = 0;
        foreach ($this->structure as $directory => $files) {
            foreach ($files as $file => $content) {
                // En yeni log dosyası artık filemtime'a göre seçiliyor
                $root->getChild($directory.'/'.$file)->lastModified(time() + $i);
                $i++;
            }
        }

        return $root;
    }

    /**
     * @return Tail
     */
    private function getCommand()
    {
        return $this->getCommandForRoot($this->giveRoot());
    }

    /**
     * @return Tail
     */
    private function getCommandForRoot($root)
    {
        $container = m::mock(new Container);
        $container->shouldReceive('basePath')->andReturn($root->url());
        $container->shouldReceive('runningUnitTests')->andReturn(false);
        $container->instance('path.storage', $root->url());
        Container::setInstance($container);

        $command = new Tail($this->getFile());
        $command->setLaravel($container);

        return $command;
    }

    /**
     * @return Filesystem
     */
    private function getFile()
    {
        $files = m::mock(new Filesystem());
        $files->shouldReceive('glob')->andReturnUsing(function ($path) {
            return Glob::glob($path);
        });

        return $files;
    }
}
