<?php

namespace Recca0120\Terminal\Tests\Console\Commands;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Recca0120\Terminal\Console\Commands\ArtisanTinker;
use Symfony\Component\Console\Tester\CommandTester;

class ArtisanTinkerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_echo()
    {
        $commandTester = $this->executeCommand('echo 123');

        self::assertStringContainsString('123', $this->lf($commandTester->getDisplay()));
    }

    public function test_var_dump()
    {
        $commandTester = $this->executeCommand('var_dump(123)');

        self::assertStringContainsString('int(123)', $this->lf($commandTester->getDisplay()));
    }

    public function test_show_object()
    {
        $commandTester = $this->executeCommand('new stdClass;');

        self::assertStringContainsString('=> stdClass', $this->lf($commandTester->getDisplay()));
    }

    public function test_show_array()
    {
        $commandTester = $this->executeCommand("['foo' => 'bar'];");

        self::assertStringContainsString('"foo": "bar"', $this->lf($commandTester->getDisplay()));
    }

    public function testHandleString()
    {
        // Dıştaki tek tırnaklar cleanCommand() tarafından shell tırnağı olarak
        // soyulur; bu yüzden string üreten bir ifade kullanılır.
        $commandTester = $this->executeCommand("strtolower('ABC')");

        self::assertStringContainsString('=> "abc"', $this->lf($commandTester->getDisplay()));
    }

    public function testNumeric()
    {
        $commandTester = $this->executeCommand('123');

        self::assertStringContainsString('=> 123', $this->lf($commandTester->getDisplay()));
    }

    protected function lf($content)
    {
        return str_replace("\r\n", "\n", $content);
    }

    /**
     * @param  string  $cmd
     * @return CommandTester
     */
    private function executeCommand($cmd)
    {
        $container = Mockery::mock(new Container);
        $container->shouldReceive('runningUnitTests')->andReturn(false);

        // Tinker, kalıcı değişkenler için Cache facade'ını kullanır;
        // test ortamında basit bir bellek-içi store yeterli.
        $container->instance('cache', new class
        {
            private array $store = [];

            public function get($key, $default = null)
            {
                return $this->store[$key] ?? $default;
            }

            public function put($key, $value, $ttl = null): void
            {
                $this->store[$key] = $value;
            }

            public function forget($key): void
            {
                unset($this->store[$key]);
            }
        });

        Facade::setFacadeApplication($container);

        $command = new ArtisanTinker();
        $command->setLaravel($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--command' => $cmd]);

        return $commandTester;
    }
}
